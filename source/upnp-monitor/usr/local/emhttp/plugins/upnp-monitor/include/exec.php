<?PHP
/* Copyright 2019, ljm42
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Markdown.php";

function parse_row($data) {
  $data = preg_replace('/   ?/', ' ', $data); // replace 2 or 3 spaces with a single space
  $arr = str_getcsv(ltrim($data), " ", "'");
  if (count($arr) < 6) return null;
  preg_match('/(.*)->(.*):(.*)/', $arr[2], $matches);
  $final = array($arr[1], $matches[1], $matches[2], $matches[3], $arr[3], $arr[4], $arr[5]);
  return($final);	
}
function print_row($dataUnsafe) {
  global $print_row_output, $localIP;
  $data = array_map('htmlspecialchars', $dataUnsafe);
  $col6 = (!$data[6]) ? "infinite" : $data[6];
  if ($data[7]) {
    // display the header
    $col7 = $data[7];
  } else {
    // calculate the contents of the delete field
    // you can only delete items if the internal address matches your local address
    if ($localIP == $data[2]) {
      $col7 = "<a href='#' onclick='deleteUpnp(\"$data[1]\",\"$data[0]\",\"$data[5]\");return false'><i class='fa fa-trash-o' title='Delete UPnP entry'></i></a>";
    } else {
      $col7 = "<a href='#' onclick='swal({title:\"Unable to delete\", text:\"Login to your router to delete this UPnP entry\"});return false'><i class='fa fa-external-link' title='Unable to delete UPnP entry'></i></a>";
    }
  }
  $print_row_output .= "<tr><td>$data[0]</td><td>$data[1]</td><td>$data[2]</td><td>$data[3]</td><td>$data[4]</td><td>$data[5]</td><td>$col6</td><td style='width:8%;text-align:center'>$col7</td></tr>\n";
}
function output_results($results) {
  $output = htmlspecialchars(implode("\n    ",$results));
  // display "->" normally, wil not cause XSS
  $output = preg_replace("/-&gt;/", "->", $output);
  return $output;
}

function getXML($link, $eth0) {
  global $timeout;
  $output = $url = "";
  $debugOutput = "## getXML\n";

  $upnp='/usr/local/emhttp/plugins/upnp-monitor/upnpXML';
  $xml = trim(@file_get_contents($upnp));
  if ($xml) {
    // confirm the xml still works
    $cmd="timeout $timeout stdbuf -o0 upnpc -u $xml -m $link -l 2>&1";
    exec($cmd, $results, $status);
    $debugOutput .= "#### Command\n    $cmd\n#### Status\n    $status\n";
    if ($status == 124) {
      // the timeout was triggered, likely UPnP was disabled after xml file created
      $debugOutput .= "#### Determination\n    ->Command timed out. UPnP not available at previous XML [$xml]\n";
      $xml = "";
    } else {
      $debugOutput .= "#### Results\n    ".output_results($results)."\n#### Determination\n";
      $addr = $eth0['IPADDR:0'];
      $resCheck = array_values(preg_grep("/Local LAN ip address : $addr/", $results));
      if (count($resCheck) > 0) {
        $debugOutput .= "    ".htmlspecialchars($resCheck[0])."\n    ->Expected LAN ip detected [$addr]\n    ->[$xml] is valid\n";
      } else {
        $debugOutput .= "    ->UPnP not available at previous XML [$xml]\n";
        $xml = "";
      }
    }
  }
  if (!$xml) {
    $debugOutput .= "***\n";
    // if no xml, or if there was an error, issue call again
    $cmd = "timeout ".(4*$timeout)." stdbuf -o0 upnpc -m $link -l 2>&1";
    exec($cmd, $results, $status);
    $debugOutput .= "#### Command\n    $cmd\n#### Status\n    $status\n";
    $debugOutput .= "#### Results\n    ".output_results($results)."\n#### Determination\n";
    $gateway = $eth0['GATEWAY:0'];
    $debugOutput .= "\n    ->gateway is [$gateway]\n\n";
    $resCheck = array_values(preg_grep("/ desc:.*{$gateway}:/", $results));
    if (count($resCheck) > 0) {
      // a connection to the gateway was found
      $url = str_replace(" desc: ", "", $resCheck[0]);
      $debugOutput .= "    ".htmlspecialchars($resCheck[0])."\n    ->Url is [$url]\n";
    } else {
      // no UPnP device found.  url not set
      $debugOutput .= "    ->No IGD device found\n";
    }

    if ($status == 124) {
      if ($url) {
        // the timeout was triggered, but a url was found
        $results = "";
        $debugOutput .= "\n    ->The command timed out, but we still found a url. Trying again for details.\n";
      } else {
        // the timeout was triggered, but url not found. UPnP is disabled.
        $debugOutput .= "\n    ->Command timed out. UPnP not available.\n";        
      }
    }
  } else {
    $url = $xml;
  }
  if (!$url) {
    // remove the xml file, if it exists
    @unlink($upnp);
    $xml = "";
  } else if ($url != $xml) {
    // new url found, and does not match previous xml. write to file.
    $xml = $url;
    file_put_contents($upnp,$xml);
  } else {
    // previous xml still valid
  }
  if ($xml && !$results) {
    // the timeout was triggered, but a url was found and stored in $upnp file. Make the call again to get proper results.
    list ($xml1, $debugOutputX, $output1, $results) = getXML($link, $eth0);
    $debugOutput .= $debugOutputX;
  } elseif ($xml) {
    $debugOutput .= "    ->The Router's IDG XML URL is [$xml]\n***\n";
  } else {
    $debugOutput .= "    ->UPnP not available on this network.\n***\n";
    $output = "RespondDisabled";
  }
  return array($xml, $debugOutput, $output, $results);
}

function getUpnpcL($xml, $link, $eth0, $results) {
  global $print_row_output, $localIP;
  $output = $publicIP = $routerIP = "";
  $debugOutput = "## parse UPNPC -L data\n";
  $debugOutput .= "#### Results\n    ".output_results($results)."\n#### Determination\n";

  $gateway = $eth0['GATEWAY:0'];
  $routerIP = preg_replace('#http://(.*):.*#i', '${1}', $xml);
  if ($routerIP === $gateway) {
    $debugOutput .= "    $xml\n    ->router IP is [$routerIP], as expected\n";
  } else {
    $debugOutput .= "    $xml\n    ->Strange... router IP detected as [$routerIP], should be [$gateway]\n";
  }

  $resCheck = array_values(preg_grep("/ExternalIPAddress = /", $results));
  if (count($resCheck) > 0) {
    $publicIP = str_replace("ExternalIPAddress = ","",$resCheck[0]);
    $debugOutput .= "\n    ".htmlspecialchars($resCheck[0])."\n    ->public IP is [$publicIP]\n";
  } else {
    $debugOutput .= "    ->No public IP found\n";
  }
  
  $addr = $eth0['IPADDR:0'];
  $resCheck = array_values(preg_grep("/Local LAN ip address : /", $results));
  if (count($resCheck) > 0) {
    $localIP = str_replace("Local LAN ip address : ","",$resCheck[0]);
    if ($localIP === $addr) {
      $debugOutput .= "\n    ".htmlspecialchars($resCheck[0])."\n    ->local IP is [$localIP], as expected\n";
    } else {
      $debugOutput .= "\n    ".htmlspecialchars($resCheck[0])."\n    ->Strange... local IP detected as [$localIP], should be [$addr]\n";
    }
  } else {
    $debugOutput .= "    ->No local IP found\n";
  }

  $resCheck = array_values(preg_grep("/ ?\d+ (TCP|UDP) /", $results));
  $debugOutput .= "\n    ->Port forwards listed below\n    ".output_results($resCheck)."\n";
  $resCheck = array_filter(array_map('parse_row', $resCheck));

  $header = array("Protocol","External Port","Internal IP","Internal Port","Description","Remote Host","Lease Time","Delete");
  $output = "RespondUpnp"."\0".$localIP."\0".$publicIP."\0".$routerIP."\0".$xml."\0";
  $print_row_output = "";
  print_row($header);
  $output .= $print_row_output."\0";
  $print_row_output = "";
  if (count($resCheck) > 0) {
    array_walk($resCheck, 'print_row');
    $output .= $print_row_output."\0";
  } else {
    $output .= "<tr><td colspan='8'>UPnP is enabled on the router, but there are currently no UPnP port forwards setup there.</td></tr>";
    $debugOutput .= "\n    ->No UPnP port forwards are setup\n";
  }
  $debugOutput .= "***\n";

  return array($debugOutput, $output);
}

function deleteEntry($xml, $link) {
  $output = $proto = $exPort = $remoteHost = "";
  $debugOutput = "## deleteEntry\n";

  $proto = strtoupper(trim($_POST['proto']));
  if ($proto != "UDP") $proto = "TCP";
  $exPort = trim($_POST['exPort']);
  if (!is_numeric($exPort)) $exPort = "";
  // $remoteHost = trim($_POST['remoteHost']);

  if ($proto && $exPort) {
    // TODO: include remoteHost in the upnpc call, if it exists
    $cmd = "upnpc -u $xml -m $link -d $exPort $proto";
    exec($cmd, $results, $status);
    $debugOutput .= "#### Command\n    $cmd\n#### Status\n    $status\n";
    $debugOutput .= "#### Results\n    ".output_results($results)."\n#### Determination\n";

    $resCheck = array_values(preg_grep("/UPNP_DeletePortMapping()/", $results));
    if (count($resCheck) > 0) {
      if (preg_match('/UPNP_DeletePortMapping\(\).*: (\d+)/',$resCheck[0],$matches)) {
        $delPortRetCode = $matches[1];
        $debugOutput .= "\n    ".htmlspecialchars($resCheck[0])."\n    ->return code is [$delPortRetCode]\n";
        // return codes defined here: https://github.com/miniupnp/miniupnp/blob/master/miniupnpc/upnpcommands.h
        switch ($delPortRetCode) {
          case 0:
            $debugOutput .= "    ->port deleted\n";
            break;
          case 402:
            $debugOutput .= "    ->Unable to delete port: 402 Invalid Args\n";
            break;
          case 606:
            $debugOutput .= "    ->Unable to delete port: 606 Action not authorized\n";
            break;
          case 714:
            $debugOutput .= "    ->Unable to delete port: 714 NoSuchEntryInArray (of ports associated with this IP)\n";
            break;
          default:
            $debugOutput .= "    ->Unable to delete port: Misc error\n";
        }
      } else {
        $debugOutput .= "    ->Unable to delete port\n";
      }
    } else {
      $debugOutput .= "    ->Unable to delete port\n";
    }
  } else {
    $debugOutput .= "    ->Unable to delete port: invalid arguments\n";
  }
  $debugOutput .= "***\n";

  return array($debugOutput, $output);
}

// ****************
// setup global vars
$timeout=3;
$localIP = "";
$print_row_output = "";

extract(parse_ini_file('/var/local/emhttp/network.ini',true));
$link = file_exists('/sys/class/net/br0') ? 'br0' : (file_exists('/sys/class/net/bond0') ? 'bond0' : 'eth0');

// to prevent Unraid from using upnpc at all, a user can remove the execute bit from the upnpc binary (or delete it) using their go script
if (!is_executable('/usr/bin/upnpc')) {
  $debugOutput = "## UPnP client not installed on Unraid\n";
  $debugOutput .= "    ->/usr/bin/upnpc is not executable or does not exist, unable to access UPnP from Unraid\n";
  $output = "RespondNotInstalled";
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
  exit;
}

// determine whether UPnP is enabled in the router
$debugOutput = "_Note: when pasting these results into the forum, `right click -> paste as plain text`_\n\n";
list ($xml, $debugOutputX, $output, $results) = getXML($link, $eth0);
$debugOutput .= $debugOutputX;
if (!$xml) {
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
  exit;
}

$task = $_POST['task'];
switch ($task) {
case 'delete':
  list ($debugOutputD, $output) = deleteEntry($xml, $link);
  $debugOutput .= $debugOutputD;
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
  break;
default:
  list ($debugOutputL, $output) = getUpnpcL($xml, $link, $eth0, $results);
  $debugOutput .= $debugOutputL;
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
}
?>
