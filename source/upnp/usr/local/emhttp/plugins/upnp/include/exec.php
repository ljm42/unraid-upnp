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
  $arr = str_getcsv(ltrim($data), " ", "'");
  if (count($arr) < 6) return null;
  preg_match('/(.*)->(.*):(.*)/', $arr[2], $matches);
  $final = array($arr[1], $matches[1], $matches[2], $matches[3], $arr[3], $arr[4], $arr[5]);
  return($final);	
}
function print_row($data) {
  global $print_row_output, $localIP;
  $print_row_output .= "<tr><td>$data[0]</td><td>$data[1]</td><td>$data[2]</td><td>$data[3]</td><td>$data[4]</td><td>$data[5]</td><td>$data[6]</td><td style='width:8%;text-align:center'>";
  if ($data[7]) {
    // display the delete header
    $print_row_output .= $data[7];
  } else {
    // calculate the contents of the delete field
    // you can only delete items if the internal address matches your local address
    if ($localIP == $data[2]) {
      $print_row_output .= "<a href='#' onclick='deleteUpnp(\"$data[1]\",\"$data[0]\",\"$data[5]\");return false'><i class='fa fa-trash-o' title='Delete UPnP entry'></i></a>";
    } else {
      $print_row_output .= "<a href='#' onclick='swal({title:\"Unable to delete\", text:\"Login to your router to delete this UPnP entry\"});return false'><i class='fa fa-minus-circle' title='Unable to delete UPnP entry'></i></a>";
    }
  }
  $print_row_output .= "</td></tr>\n";
}

function getXML($link, $eth0) {
  global $timeout;
  $output = $url = "";
  $debugOutput = "## getXML\n";

  $upnp='/usr/local/emhttp/plugins/upnp/upnpXML';
  $xml = trim(@file_get_contents($upnp));
  if ($xml) {
    // confirm the xml still works
    $cmd1="timeout $timeout upnpc -u $xml -m $link -l 2>&1";
    exec($cmd1, $results1, $status1);
    $debugOutput .= "#### Command\n    $cmd1\n#### Status\n    $status1\n";
    if ($status1 == 124) {
      // the timeout was triggered, likely UPnP was disabled after xml file created
      $debugOutput .= "#### Determination\n    ->Command timed out. UPnP not available at previous XML [$xml]\n";
      $xml = "";
    } else {
      $debugOutput .= "#### Results\n    ".implode("\n    ",$results1)."\n#### Determination\n";
      $addr = $eth0['IPADDR:0'];
      $resCheck1 = array_values(preg_grep("/Local LAN ip address : $addr/", $results1));
      if (count($resCheck1) > 0) {
        $debugOutput .= "    $resCheck1[0]\n    ->Expected LAN ip detected [$addr]\n    ->[$xml] is valid\n";
      } else {
        $debugOutput .= "    ->UPnP not available at previous XML [$xml]\n";
        $xml = "";
      }
    }
  }
  if (!$xml) {
    $debugOutput .= "***\n";
    // if no xml, or if there was an error, issue call again
    $cmd2 = "timeout ".(2*$timeout)." upnpc -m $link -l 2>&1";
    exec($cmd2, $results2, $status2);
    $debugOutput .= "#### Command\n    $cmd2\n#### Status\n    $status2\n";
    if ($status2 == 124) {
      // the timeout was triggered, UPnP disabled.  url not set
      $debugOutput .= "#### Determination\n    ->Command timed out. UPnP not available.\n";
    } else {
      $debugOutput .= "#### Results\n    ".implode("\n    ",$results2)."\n#### Determination\n";
      $gateway = $eth0['GATEWAY:0'];
      $debugOutput .= "\n    ->gateway is [$gateway]\n\n";
      $resCheck2A = array_values(preg_grep("/ desc:.*{$gateway}:/", $results2));
      if (count($resCheck2A) > 0) {
        // a connection to the gateway was found
        $url = str_replace(" desc: ", "", $resCheck2A[0]);
        $debugOutput .= "    $resCheck2A[0]\n    ->Url is [$url]\n";
      } else {
        // no UPnP device found.  url not set
        $debugOutput .= "    ->No IGD device found\n";
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
  if ($xml) {
    $debugOutput .= "    ->The Router's IDG XML URL is [$xml]\n***\n";
  } else {
    $debugOutput .= "    ->UPnP not available on this network.\n***\n";
    $output = "RespondDisabled";
  }
  return array($xml, $debugOutput, $output);
}

function getUpnpcL($xml, $link, $eth0) {
  global $timeout, $print_row_output, $localIP;
  $output = $publicIP = $routerIP = "";
  $debugOutput = "## parse UPNPC -L data\n";

  $cmd3 = "timeout $timeout upnpc -u $xml -m $link -l 2>&1";
  exec($cmd3, $results3, $status3);
  $debugOutput .= "#### Command\n    $cmd3\n#### Status\n    $status3\n";

  if ($status3 == 124) {
    // the timeout was triggered. This should not happen since we just got a valid xml
    $debugOutput .= "#### Determination\n    ->Command timed out. UPnP not available.\n";
    $output = "RespondDisabled";

  } else {
    $debugOutput .= "#### Results\n    ".implode("\n    ",$results3)."\n#### Determination\n";

    $gateway = $eth0['GATEWAY:0'];
    $routerIP = preg_replace('#http://(.*):.*#i', '${1}', $xml);
    if ($routerIP === $gateway) {
      $debugOutput .= "    $xml\n    ->router IP is [$routerIP], as expected\n";
    } else {
      $debugOutput .= "    $xml\n    ->Strange... router IP detected as [$routerIP], should be [$gateway]\n";
    }

    $resCheck3A = array_values(preg_grep("/ExternalIPAddress = /", $results3));
    if (count($resCheck3A) > 0) {
      $publicIP = str_replace("ExternalIPAddress = ","",$resCheck3A[0]);
      $debugOutput .= "\n    $resCheck3A[0]\n    ->public IP is [$publicIP]\n";
    } else {
      $debugOutput .= "    ->No public IP found\n";
    }
    
    $addr = $eth0['IPADDR:0'];
    $resCheck3B = array_values(preg_grep("/Local LAN ip address : /", $results3));
    if (count($resCheck3B) > 0) {
      $localIP = str_replace("Local LAN ip address : ","",$resCheck3B[0]);
      if ($localIP === $addr) {
        $debugOutput .= "\n    $resCheck3B[0]\n    ->local IP is [$localIP], as expected\n";
      } else {
        $debugOutput .= "\n    $resCheck3B[0]\n    ->Strange... local IP detected as [$localIP], should be [$addr]\n";
      }
    } else {
      $debugOutput .= "    ->No local IP found\n";
    }

    $resCheck3C = array_values(preg_grep("/ ?\d+ (TCP|UDP) /", $results3));
    $debugOutput .= "\n    ->Port forwards listed below\n    ".implode("\n    ",$resCheck3C)."\n";
    $resCheck3C = array_filter(array_map('parse_row', $resCheck3C));

    $header = array("Protocol","External Port","Internal IP","Internal Port","Description","Remote Host","Lease Time","Delete");
    $output = "RespondUpnp"."\0".$localIP."\0".$publicIP."\0".$routerIP."\0".substr($xml,3)."\0";
    $print_row_output = "";
    print_row($header);
    $output .= $print_row_output."\0";
    $print_row_output = "";
    if (count($resCheck3C) > 0) {
      array_walk($resCheck3C, 'print_row');
      $output .= $print_row_output."\0";
    } else {
      $output .= "<tr><td colspan='8'>UPnP is enabled on the router, but there are currently no UPnP port forwards setup there.</td></tr>";
      $debugOutput .= "\n    ->No UPnP port forwards are setup\n";
    }
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
    $cmd5 = "upnpc -u $xml -m $link -d $exPort $proto";
    exec($cmd5, $results5, $status5);
    $debugOutput .= "#### Command\n    $cmd5\n#### Status\n    $status5\n";
    $debugOutput .= "#### Results\n    ".implode("\n    ",$results5)."\n#### Determination\n";

    $resCheck5A = array_values(preg_grep("/UPNP_DeletePortMapping()/", $results5));
    if (count($resCheck5A) > 0) {
      if (preg_match('/UPNP_DeletePortMapping\(\).*: (\d+)/',$resCheck5A[0],$matches5A)) {
        $delPortRetCode = $matches5A[1];
        $debugOutput .= "\n    $resCheck5A[0]\n    ->return code is [$delPortRetCode]\n";
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
$timeout=6;
$localIP = "";
$print_row_output = "";

extract(parse_ini_file('/var/local/emhttp/network.ini',true));
$link = file_exists('/sys/class/net/br0') ? 'br0' : (file_exists('/sys/class/net/bond0') ? 'bond0' : 'eth0');

// to prevent Unraid from using upnpc at all, a user can delete or rename the upnpc executable in their go script
if (!is_executable('/usr/bin/upnpc')) {
  $debugOutput = "## UPnP client not installed on Unraid\n";
  $debugOutput .= "    ->/usr/bin/upnpc is not executable or does not exist, unable to access UPnP from Unraid\n";
  $output = "RespondNotInstalled";
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
  exit;
}

// determine whether UPnP is enabled in the router
$debugOutput = "_Note: when pasting these results into the forum, `right click -> paste as plain text`_\n\n";
list ($xml, $debugOutputX, $output) = getXML($link, $eth0);
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
  list ($debugOutputL, $output) = getUpnpcL($xml, $link, $eth0);
  $debugOutput .= $debugOutputL;
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
}
?>
