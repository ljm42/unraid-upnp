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

function getXML() {
  global $link, $timeout;
  $output = $url = $err = "";
  $debugOutput = "## getXML\n";

  $fileXML='/usr/local/emhttp/plugins/upnp/upnpXML';
  $xml = trim(@file_get_contents($fileXML));
  if ($xml) {
    // confirm the xml still works
    $cmd1="timeout $timeout upnpc $xml $link -l 2>&1";
    exec($cmd1, $results1, $status1);
    $debugOutput .= "#### Command\n    $cmd1\n#### Status\n    $status1\n";
    if ($status1 == 124) {
      // the timeout was triggered, likely UPnP was disabled after xml file created
      $err = 1;
      $debugOutput .= "#### Determination\n    ->Command timed out. UPnP not available at previous XML [$xml].\n";
    } else {
      $debugOutput .= "#### Results\n    ".implode("\n    ",$results1)."\n#### Determination\n";
      preg_match('#-u (http://.*/).*#',$xml,$matches);
      $urlbase = $matches[1];
      $debugOutput .= "    ->urlbase is [$urlbase]\n\n";
      $resCheck1 = array_values(preg_grep("#Found valid IGD : {$urlbase}#", $results1));
      if (count($resCheck1) > 0) {
        $debugOutput .= "    $resCheck1[0]\n    ->[$xml] is valid\n";
      } else {
        $err = 1;
        $debugOutput .= "    ->UPnP not available at previous XML [$xml].\n";
      }
    }
  }
  if (!$xml || $err) {
    $debugOutput .= "***\n";
    // if no xml, or if there was an error, issue call again
    $cmd2 = "timeout ".(2*$timeout)." upnpc -l 2>/dev/null";
    exec($cmd2, $results2, $status2);
    $debugOutput .= "#### Command\n    $cmd2\n#### Status\n    $status2\n";
    if ($status2 == 124) {
      // the timeout was triggered, UPnP disabled.  url not set
      $debugOutput .= "#### Determination\n    ->Command timed out. UPnP not available.\n";
    } else {
      $debugOutput .= "#### Results\n    ".implode("\n    ",$results2)."\n#### Determination\n";
      $resCheck2A = array_values(preg_grep("/Found valid IGD/", $results2));
      if (count($resCheck2A) > 0) {
        // a valid IGD exists
        $debugOutput .= "    $resCheck2A[0]\n";
        $resCheck2B = array_values(preg_grep("/ desc: /", $results2));
        if (count($resCheck2B) > 0) {
          // and here is the url
          $url = str_replace(" desc: ", "", $resCheck2B[0]);
          $debugOutput .= "    $resCheck2B[0]\n    ->Url is [$url]\n";
        } else {
          // no UPnP device found.  url not set
          $debugOutput .= "    ->No UPnP device found\n";
        }
      } else {
        // no UPnP device found.  url not set
        $debugOutput .= "    ->No IGD device found\n";
      }
    }
  } else {
    $url = substr($xml, 3);
  }
  if (!$url) {
    // remove the xml file, if it exists
    @unlink($fileXML);
    $xml = '';
  } else if ($url != substr($xml,3)) {
    // new url found, and does not match previous xml. write to file.
    $xml = '-u '.$url;
    $fp = fopen($fileXML, 'w');
    fwrite($fp, $xml);
    fclose($fp);
  } else {
    // previous xml and xml file are still valid
  }
  if ($xml) {
    $debugOutput .= "    ->The Router's IDG XML URL is [$xml]\n***\n";
  } else {
    $debugOutput .= "    ->UPnP not available on this network.\n***\n";
    $output = "RespondDisabled";
  }
  return array($xml, $debugOutput, $output);
}

function getUpnpcL($xml) {
  global $link, $timeout, $print_row_output, $localIP;
  $output = $publicIP = $routerIP = "";
  $debugOutput = "## parse UPNPC -L data\n";

  $cmd3 = "timeout $timeout upnpc $xml $link -l 2>/dev/null";
  exec($cmd3, $results3, $status3);
  $debugOutput .= "#### Command\n    $cmd3\n#### Status\n    $status3\n";

  if ($status3 == 124) {
    // the timeout was triggered. This should not happen since we just got a valid xml
    $debugOutput .= "#### Determination\n    ->Command timed out. UPnP not available.\n";
    $output = "RespondDisabled";

  } else {
    $debugOutput .= "#### Results\n    ".implode("\n    ",$results3)."\n#### Determination\n";

    $routerIP = preg_replace('#\-u http://(.*):.*#i', '${1}', $xml);
    $debugOutput .= "    $xml\n    ->router IP is [$routerIP]\n";

    $resCheck3A = array_values(preg_grep("/ExternalIPAddress = /", $results3));
    if (count($resCheck3A) > 0) {
      $publicIP = str_replace("ExternalIPAddress = ","",$resCheck3A[0]);
      $debugOutput .= "\n    $resCheck3A[0]\n    ->public IP is [$publicIP]\n";
    } else {
      $debugOutput .= "    ->No public IP found\n";
    }

    $resCheck3B = array_values(preg_grep("/Local LAN ip address : /", $results3));
    if (count($resCheck3B) > 0) {
      $localIP = str_replace("Local LAN ip address : ","",$resCheck3B[0]);
      $debugOutput .= "\n    $resCheck3B[0]\n    ->local IP is [$localIP]\n";
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

function deleteEntry($xml) {
  global $link;
  $output = $proto = $exPort = $remoteHost = "";
  $debugOutput = "## deleteEntry\n";

  $proto = strtoupper(trim($_POST['proto']));
  if ($proto != "UDP") $proto = "TCP";
  $exPort = trim($_POST['exPort']);
  if (!is_numeric($exPort)) $exPort = "";
  // $remoteHost = trim($_POST['remoteHost']);

  if ($proto && $exPort) {
    // TODO: include remoteHost in the upnpc call, if it exists
    $cmd5 = "upnpc $xml $link -d $exPort $proto";
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
$link = "-m ".(file_exists('/sys/class/net/br0') ? 'br0' : (file_exists('/sys/class/net/bond0') ? 'bond0' : 'eth0'));
$timeout=6;
$localIP = "";
$print_row_output = "";

// to prevent Unraid from using upnpc at all, a user can delete or rename the upnpc executable in their go script
if (!file_exists('/usr/bin/upnpc')) {
  $unraid = parse_ini_file('/etc/unraid-version');
  $minver = "6.7.0-rc1"; // TODO: verify minimum version
  $debugOutput = "## upnpc not installed\n";
  $debugOutput .= "    Minimum Unraid version: [$minver]\n    Your Unraid version: [".$unraid['version']."]\n";
  $debugOutput .= "    ->Your version is ".(version_compare($minver, $unraid['version'], '<') ? "" : "not ")."sufficient.\n\n";
  $debugOutput .= "    ->/usr/bin/upnpc does not exist, unable to use UPnP\n";
  $output = "RespondNotInstalled";
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
  exit;
}

// determine whether UPnP is enabled in the router
list ($xml, $debugOutput, $output) = getXML();
if (!$xml) {
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
  exit;
}

$task = $_POST['task'];
switch ($task) {
case 'delete':
  list ($debugOutputD, $output) = deleteEntry($xml);
  $debugOutput .= $debugOutputD;
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
  break;
default:
  list ($debugOutputL, $output) = getUpnpcL($xml);
  $debugOutput .= $debugOutputL;
  echo Markdown($debugOutput)."\0".$debugOutput."\0".$output;
}
?>
