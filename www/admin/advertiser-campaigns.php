<?php

/*
+---------------------------------------------------------------------------+
| Openads v${RELEASE_MAJOR_MINOR}                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2007 Openads Limited                                   |
| For contact details, see: http://www.openads.org/                         |
|                                                                           |
| Copyright (c) 2000-2003 the phpAdsNew developers                          |
| For contact details, see: http://www.phpadsnew.com/                       |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

// Require the initialisation file
require_once '../../init.php';

// Required files
require_once MAX_PATH . '/www/admin/lib-maintenance-priority.inc.php';
require_once MAX_PATH . '/lib/OA/Dal.php';
require_once MAX_PATH . '/lib/OA/Dll.php';
require_once MAX_PATH . '/www/admin/config.php';
require_once MAX_PATH . '/www/admin/lib-statistics.inc.php';
require_once MAX_PATH . '/lib/OA/Permission.php';
require_once MAX_PATH . '/lib/pear/Date.php';

phpAds_registerGlobalUnslashed('expand', 'collapse', 'hideinactive', 'listorder', 'orderdirection');

OA_Permission::enforceAccount(OA_ACCOUNT_ADMIN, OA_ACCOUNT_MANAGER, OA_ACCOUNT_ADVERTISER);
OA_Permission::checkAccessToObject('clients', $clientid);

if (OA_Permission::isAccount(OA_ACCOUNT_ADMIN) && !is_numeric($clientid)) {
    header("Location: advertiser-index.php");
}

/*-------------------------------------------------------*/
/* HTML framework                                        */
/*-------------------------------------------------------*/

if (isset($session['prefs']['advertiser-index.php']['listorder'])) {
    $navorder = $session['prefs']['advertiser-index.php']['listorder'];
} else {
    $navorder = '';
}

if (isset($session['prefs']['advertiser-index.php']['orderdirection'])) {
 $session['prefs']['advertiser-index.php']['orderdirection'];
    $navdirection = $session['prefs']['advertiser-index.php']['orderdirection'];
} else {
	$navdirection = '';
}

// Get other clients
$doClients = OA_Dal::factoryDO('clients');

// Unless admin, restrict results shown.
if (OA_Permission::isAccount(OA_ACCOUNT_MANAGER)) {
    $doClients->agencyid = OA_Permission::getEntityId();
} elseif (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
    $doClients->clientid = OA_Permission::getEntityId();
}

$doClients->addListOrderBy($navorder, $navdirection);
$doClients->find();

while ($doClients->fetch() && $row = $doClients->toArray()) {
	phpAds_PageContext(
		phpAds_buildName ($row['clientid'], $row['clientname']),
		"advertiser-campaigns.php?clientid=".$row['clientid'],
		$clientid == $row['clientid']
	);
}

phpAds_PageShortcut($strClientHistory, 'stats.php?entity=advertiser&breakdown=history&clientid='.$clientid, 'images/icon-statistics.gif');

if (OA_Permission::isAccount(OA_ACCOUNT_MANAGER) || OA_Permission::isAccount(OA_ACCOUNT_ADMIN)) {
    phpAds_PageHeader("4.1.3");
	echo "<img src='images/icon-advertiser.gif' align='absmiddle'>&nbsp;<b>".phpAds_getClientName($clientid)."</b><br /><br /><br />";
	phpAds_ShowSections(array("4.1.2", "4.1.3"//, "4.1.4"
	));
} else {
    phpAds_PageHeader("2");
	echo "<img src='images/icon-advertiser.gif' align='absmiddle'>&nbsp;<b>".phpAds_getClientName($clientid)."</b><br /><br /><br />";
	//phpAds_ShowSections();
}

/*-------------------------------------------------------*/
/* Get preferences                                       */
/*-------------------------------------------------------*/

if (!isset($hideinactive)) {
	if (isset($session['prefs']['advertiser-campaigns.php'][$clientid]['hideinactive'])) {
		$hideinactive = $session['prefs']['advertiser-campaigns.php'][$clientid]['hideinactive'];
	} else {
	   $pref = &$GLOBALS['_MAX']['PREF'];
	   $hideinactive = ($pref['gui_hide_inactive'] == 't');
	}
}

if (!isset($listorder)) {
	if (isset($session['prefs']['advertiser-campaigns.php'][$clientid]['listorder'])) {
		$listorder = $session['prefs']['advertiser-campaigns.php'][$clientid]['listorder'];
	} else {
		$listorder = '';
	}
}

if (!isset($orderdirection)) {
	if (isset($session['prefs']['advertiser-campaigns.php'][$clientid]['orderdirection'])) {
		$orderdirection = $session['prefs']['advertiser-campaigns.php'][$clientid]['orderdirection'];
	} else {
		$orderdirection = '';
	}
}

if (isset($session['prefs']['advertiser-campaigns.php'][$clientid]['nodes'])) {
	$node_array = explode (",", $session['prefs']['advertiser-campaigns.php'][$clientid]['nodes']);
} else {
	$node_array = array();
}

/*-------------------------------------------------------*/
/* Main code                                             */
/*-------------------------------------------------------*/

// Get clients & campaign and build the tree

$doCampaigns = OA_Dal::factoryDO('campaigns');
$doCampaigns->clientid = $clientid;

$doCampaigns->addListOrderBy($listorder, $orderdirection);
$doCampaigns->find();

while ($doCampaigns->fetch() && $row_campaigns = $doCampaigns->toArray()) {
	$campaigns[$row_campaigns['campaignid']]['campaignid']   = $row_campaigns['campaignid'];

    // mask campaign name if anonymous campaign
    $campaign_details = Admin_DA::getPlacement($row_campaigns['campaignid']);
    $row_campaigns['campaignname'] = MAX_getPlacementName($campaign_details);

	$campaigns[$row_campaigns['campaignid']]['campaignname'] = $row_campaigns['campaignname'];
	$campaigns[$row_campaigns['campaignid']]['impressions']  = phpAds_formatNumber($row_campaigns['views']);
	$campaigns[$row_campaigns['campaignid']]['clicks']       = phpAds_formatNumber($row_campaigns['clicks']);
	$campaigns[$row_campaigns['campaignid']]['conversions']  = phpAds_formatNumber($row_campaigns['conversions']);
	if (($row_campaigns['activate']) && ($row_campaigns['activate'] != '0000-00-00')) {
	   $oActivateDate = &new Date($row_campaigns['activate']);
	   $campaigns[$row_campaigns['campaignid']]['activate']  = $oActivateDate->format($date_format);
    } else {
       $campaigns[$row_campaigns['campaignid']]['activate']  = '-';
    }
	if (($row_campaigns['activate']) && ($row_campaigns['expire'] != '0000-00-00')) {
	   $oExpireDate = &new Date($row_campaigns['expire']);
	   $campaigns[$row_campaigns['campaignid']]['expire']    = $oExpireDate->format($date_format);
    } else {
       $campaigns[$row_campaigns['campaignid']]['expire']    = '-';
    }
    if ($row_campaigns['priority'] == -1) {
        $campaigns[$row_campaigns['campaignid']]['priority'] = $strExclusive;
    } elseif ($row_campaigns['priority'] == 0) {
        $campaigns[$row_campaigns['campaignid']]['priority'] = $strLow;
    } else {
        $campaigns[$row_campaigns['campaignid']]['priority'] = $strHigh . ' (' . $row_campaigns['priority'] . ')';
    }
	$campaigns[$row_campaigns['campaignid']]['expand'] = 0;
	$campaigns[$row_campaigns['campaignid']]['count']  = 0;
	$campaigns[$row_campaigns['campaignid']]['status'] = $row_campaigns['status'];
    $campaigns[$row_campaigns['campaignid']]['anonymous'] = $row_campaigns['anonymous'];
}


$doBanners = OA_Dal::factoryDO('banners');
$doBanners->selectAs(array('storagetype'), 'type');
$doBanners->addListOrderBy($listorder, $orderdirection);
$doBanners->find();

while ($doBanners->fetch() && $row_banners = $doBanners->toArray()) {
    if (isset($campaigns[$row_banners['campaignid']])) {
        $banners[$row_banners['bannerid']] = $row_banners;

        // mask banner name if anonymous campaign
        $campaign_details = Admin_DA::getPlacement($row_banners['campaignid']);
        $campaignAnonymous = $campaign_details['anonymous'] == 't' ? true : false;
        $banners[$row_banners['bannerid']]['description'] = MAX_getAdName($row_banners['description'], null, null, $campaignAnonymous, $row_banners['bannerid']);

        $campaigns[$row_banners['campaignid']]['count']++;
    }
}

// Add ID found in expand to expanded nodes
if (isset($expand) && $expand != '') {
	switch ($expand) {
		case 'all' :	$node_array   = array();
						if (isset($campaigns)) reset($campaigns); while (list($key,) = each($campaigns)) $node_array[] = $key;
						break;
		case 'none':	$node_array   = array();
						break;
		default:		$node_array[] = $expand;
						break;
	}
}

$node_array_size = sizeof($node_array);
for ($i=0; $i < $node_array_size;$i++) {
	if (isset($collapse) && $collapse == $node_array[$i]) {
		unset ($node_array[$i]);
	} else {
		if (isset($campaigns[$node_array[$i]])) {
			$campaigns[$node_array[$i]]['expand'] = 1;
		}
	}
}

// Build Tree
$campaignshidden = 0;
if (isset($banners) && is_array($banners) && count($banners) > 0) {
	// Add banner to campaigns
	reset ($banners);
	while (list ($bkey, $banner) = each ($banners)) {
		if ($hideinactive == false || $banner['status'] == OA_ENTITY_STATUS_RUNNING) {
			$campaigns[$banner['campaignid']]['banners'][$bkey] = $banner;
		}
	}
	unset ($banners);
}

if (isset($campaigns) && is_array($campaigns) && count($campaigns) > 0) {
	reset ($campaigns);
	while (list ($key, $campaign) = each ($campaigns)) {
		if (!isset($campaign['banners'])) {
			$campaign['banners'] = array();
		}
		if ($hideinactive == true && ($campaign['status'] != OA_ENTITY_STATUS_RUNNING || $campaign['status'] == OA_ENTITY_STATUS_RUNNING &&
			count($campaign['banners']) == 0 && count($campaign['banners']) < $campaign['count'])) {
			$campaignshidden++;
			unset($campaigns[$key]);
		}
	}
}

if (!OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
    echo "<img src='images/icon-campaign-new.gif' border='0' align='absmiddle'>&nbsp;";
    echo "<a href='campaign-edit.php?clientid=".$clientid."' accesskey='".$keyAddNew."'>".$strAddCampaign_Key."</a>&nbsp;&nbsp;";
    phpAds_ShowBreak();
    echo "<br /><br />";
}
?>


<?php
echo "<table border='0' width='100%' cellpadding='0' cellspacing='0'>";

echo "<tr height='25'>";
echo "<td height='25' width='40%'><b>&nbsp;&nbsp;<a href='advertiser-campaigns.php?clientid=".$clientid."&listorder=name'>".$GLOBALS['strName']."</a>";

if (($listorder == "name") || ($listorder == "")) {
	if  (($orderdirection == "") || ($orderdirection == "down")) {
		echo ' <a href="advertiser-campaigns.php?clientid='.$clientid.'&orderdirection=up">';
		echo '<img src="images/caret-ds.gif" border="0" alt="" title="">';
	} else {
		echo ' <a href="advertiser-campaigns.php?clientid='.$clientid.'&orderdirection=down">';
		echo '<img src="images/caret-u.gif" border="0" alt="" title="">';
	}
	echo '</a>';
}

echo '</b></td>';

echo '<td height="25"><b><a href="advertiser-campaigns.php?clientid='.$clientid.'&listorder=status">Status</a>';
if ($listorder == "status") {
    if  (($orderdirection == "") || ($orderdirection == "down")) {
        echo ' <a href="advertiser-campaigns.php?clientid='.$clientid.'&orderdirection=up">';
        echo '<img src="images/caret-ds.gif" border="0" alt="" title="">';
    } else {
        echo ' <a href="advertiser-campaigns.php?clientid='.$clientid.'&orderdirection=down">';
        echo '<img src="images/caret-u.gif" border="0" alt="" title="">';
    }
    echo '</a>';
}
echo "</td>";

echo '<td height="25"><b><a href="advertiser-campaigns.php?clientid='.$clientid.'&listorder=id">'.$GLOBALS['strID'].'</a>';

if ($listorder == "id") {
	if  (($orderdirection == "") || ($orderdirection == "down")) {
		echo ' <a href="advertiser-campaigns.php?clientid='.$clientid.'&orderdirection=up">';
		echo '<img src="images/caret-ds.gif" border="0" alt="" title="">';
	} else {
		echo ' <a href="advertiser-campaigns.php?clientid='.$clientid.'&orderdirection=down">';
		echo '<img src="images/caret-u.gif" border="0" alt="" title="">';
	}
	echo '</a>';
}

echo '</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>';
echo "<td height='25'>&nbsp;</td>";
echo "<td height='25'>&nbsp;</td>";
echo "<td height='25'>&nbsp;</td>";
echo "</tr>";

echo "<tr class='break'><td colspan='6' ></td></tr>";

if (!isset($campaigns) || !is_array($campaigns) || count($campaigns) == 0) {
	echo "<tr height='25' bgcolor='#F6F6F6'><td height='25' colspan='5'>";
	echo "&nbsp;&nbsp;".$strNoCampaigns;
	echo "</td></tr>";
	echo "<tr class='break'><td colspan='6' ></td></tr>";

} else {
	$i=0;
	foreach (array_keys($campaigns) as $ckey) {
		// Icon & name
		echo "<tr height='25' ".($i%2==0?"bgcolor='#F6F6F6'":"")."><td height='25'>";
		echo "&nbsp;";
		if (isset($campaigns[$ckey]['banners'])) {
			if ($campaigns[$ckey]['expand'] == '1') {
				echo "<a href='advertiser-campaigns.php?clientid=".$clientid."&collapse=".$campaigns[$ckey]['campaignid']."'><img src='images/triangle-d.gif' align='absmiddle' border='0'></a>&nbsp;";
			} else {
				echo "<a href='advertiser-campaigns.php?clientid=".$clientid."&expand=".$campaigns[$ckey]['campaignid']."'><img src='images/".$phpAds_TextDirection."/triangle-l.gif' align='absmiddle' border='0'></a>&nbsp;";
			}
		} else {
		    echo "<img src='images/spacer.gif' height='16' width='16' align='absmiddle'>&nbsp;";
		}
		if ($campaigns[$ckey]['status'] == OA_ENTITY_STATUS_RUNNING) {
			echo "<img src='images/icon-campaign.gif' align='absmiddle'>&nbsp;";
		} else {
			echo "<img src='images/icon-campaign-d.gif' align='absmiddle'>&nbsp;";
		}
		if (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
		    if (OA_Permission::isAllowed(OA_PERM_BANNER_ACTIVATE) || OA_Permission::isAllowed(OA_PERM_BANNER_EDIT)) {
        		echo "<a href='campaign-banners.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."'>".$campaigns[$ckey]['campaignname']."</td>";
		    } else {
		        echo $campaigns[$ckey]['campaignname'];
		    }
		} else {
    		echo "<a href='campaign-edit.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."'>".$campaigns[$ckey]['campaignname']."</td>";
		}
		echo "</td>";

    // status
        if ($campaigns[$ckey]['status'] == -1) {
            echo "<td class=\"sts sts-pending\">Pending</td>";
        } elseif ($campaigns[$ckey]['status'] == 0) {
            echo "<td class=\"sts sts-accepted\">Runing</td>";
        } elseif ($campaigns[$ckey]['status'] == 1) {
            echo "<td class=\"sts sts-paused\">Paused</td>";
        } elseif ($campaigns[$ckey]['status'] == 2) {
            echo "<td class=\"sts not-started\">Not started yet</td>";
        } elseif ($campaigns[$ckey]['status'] == 3) {
            echo "<td class=\"sts sts-finished\">Finished</td>";
        } elseif ($campaigns[$ckey]['status'] == 21) {
            echo "<td class=\"sts sts-awaiting\"><a href='campaign-edit.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."'>Awaiting approval &raquo;</a></td>";
        } elseif ($campaigns[$ckey]['status'] == 22) {
            echo "<td class=\"sts sts-rejected\">Rejected</td>";
        }

        //echo "<td height='25'><span class='sts-awaiting'><a href='campaign-edit.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."'>Awaiting approval &raquo;</a></span></td>";
		// ID
		echo "<td height='25'>".$campaigns[$ckey]['campaignid']."</td>";

		// Button 1
		echo "<td height='25' align='".$phpAds_TextAlignRight."'>";
		if (($campaigns[$ckey]['expand'] == '1' || !isset($campaigns[$ckey]['banners'])) && !OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER))
			echo "<a href='banner-edit.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."'><img src='images/icon-banner-new.gif' border='0' align='absmiddle' alt='$strCreate'>&nbsp;$strCreate</a>&nbsp;&nbsp;&nbsp;&nbsp;";
		else
			echo "&nbsp;";
		echo "</td>";

		// Button 2
		echo "<td height='25' align='".$phpAds_TextAlignRight."'>";
		echo "<a href='campaign-banners.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."'><img src='images/icon-overview.gif' border='0' align='absmiddle' alt='$strOverview'>&nbsp;$strOverview</a>&nbsp;&nbsp;&nbsp;&nbsp;";
		echo "</td>";

		// Button 3
		echo "<td height='25' align='".$phpAds_TextAlignRight."'>";
		if (!OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
    		echo "<a href='campaign-delete.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."&returnurl=advertiser-campaigns.php'".phpAds_DelConfirm($strConfirmDeleteCampaign)."><img src='images/icon-recycle.gif' border='0' align='absmiddle' alt='$strDelete'>&nbsp;$strDelete</a>&nbsp;&nbsp;&nbsp;&nbsp;";
		} else {
		    echo "&nbsp;";
		}
		echo "</td></tr>";

		if ($campaigns[$ckey]['expand'] == '1' && isset($campaigns[$ckey]['banners'])) {
			$banners = $campaigns[$ckey]['banners'];
			foreach (array_keys($banners) as $bkey) {
				$name = $strUntitled;
				if (isset($banners[$bkey]['alt']) && $banners[$bkey]['alt'] != '') $name = $banners[$bkey]['alt'];
				if (isset($banners[$bkey]['description']) && $banners[$bkey]['description'] != '') $name = $banners[$bkey]['description'];

				$name = phpAds_breakString ($name, '30');

				// Divider
				echo "<tr height='1' bgcolor='#f6f6f6'>";
				echo "<td></td>";
				echo "<td colspan='5' bgcolor='#bbbbbb'></td>";
				echo "</tr>";


				// Icon & name
				echo "<tr height='25' ".($i%2==0?"bgcolor='#F6F6F6'":"").">";
				echo "<td height='25'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";

				if ($banners[$bkey]['status'] == OA_ENTITY_STATUS_RUNNING && $campaigns[$ckey]['status'] == OA_ENTITY_STATUS_RUNNING) {
					if ($banners[$bkey]['type'] == 'html')
						echo "<img src='images/icon-banner-html.gif' align='absmiddle'>";
					elseif ($banners[$bkey]['type'] == 'txt')
						echo "<img src='images/icon-banner-text.gif' align='absmiddle'>";
					elseif ($banners[$bkey]['type'] == 'url')
						echo "<img src='images/icon-banner-url.gif' align='absmiddle'>";
					else
						echo "<img src='images/icon-banner-stored.gif' align='absmiddle'>";
				} else {
					if ($banners[$bkey]['type'] == 'html')
						echo "<img src='images/icon-banner-html-d.gif' align='absmiddle'>";
					elseif ($banners[$bkey]['type'] == 'txt')
						echo "<img src='images/icon-banner-text-d.gif' align='absmiddle'>";
					elseif ($banners[$bkey]['type'] == 'url')
						echo "<img src='images/icon-banner-url-d.gif' align='absmiddle'>";
					else
						echo "<img src='images/icon-banner-stored-d.gif' align='absmiddle'>";
				}

				echo "&nbsp;";
				if (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER) && !OA_Permission::isAllowed(OA_PERM_BANNER_EDIT)) {
				    echo $name;
				} else {
    				echo "<a href='banner-edit.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."&bannerid=".$banners[$bkey]['bannerid']."'>".$name."</a></td>";
				}

                //empty cell to match status
                echo "<td height='25'>&nbsp;</td>";

				// ID
				echo "<td height='25'>".$banners[$bkey]['bannerid']."</td>";

				// Empty
				echo "<td>&nbsp;</td>";

				// Button 2
				echo "<td height='25' align='".$phpAds_TextAlignRight."'>";
				if (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
				    echo "&nbsp;";
				} else {
    				echo "<a href='banner-acl.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."&bannerid=".$banners[$bkey]['bannerid']."'><img src='images/icon-acl.gif' border='0' align='absmiddle' alt='$strACL'>&nbsp;$strACL</a>&nbsp;&nbsp;&nbsp;&nbsp;";
				}
				echo "</td>";

				// Button 3
				echo "<td height='25' align='".$phpAds_TextAlignRight."'>";
				if (!OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
    				echo "<a href='banner-delete.php?clientid=".$clientid."&campaignid=".$campaigns[$ckey]['campaignid']."&bannerid=".$banners[$bkey]['bannerid']."&returnurl=advertiser-campaigns.php'".phpAds_DelConfirm($strConfirmDeleteBanner)."><img src='images/icon-recycle.gif' border='0' align='absmiddle' alt='$strDelete'>&nbsp;$strDelete</a>&nbsp;&nbsp;&nbsp;&nbsp;";
				}
				echo "</td></tr>";
			}
		}

		if ($pref['gui_show_campaign_info']) {
		      //divider
            echo "<tr height='1' bgcolor='#f6f6f6'>";
            echo "<td></td>";
            echo "<td colspan='5' bgcolor='#bbbbbb'></td>";
            echo "</tr>";

			echo "<tr ".($i%2==0?"bgcolor='#F6F6F6'":"")."><td>&nbsp;</td><td colspan='5'>";
			echo "<table width='100%' cellpadding='0' cellspacing='0' border='0'>";
			echo "<tr height='25'>";
			echo "<td width='20%'>".$strImpressionsBooked.":</td>";
			echo "<td width='20%' align='right'>".($campaigns[$ckey]['impressions'] >= 0 ? $campaigns[$ckey]['impressions'] : $strUnlimited)."</td>";
			echo "<td width='10%'>&nbsp;</td>";
			echo "<td width='20%'>".$strActivationDate.":</td>";
			echo "<td width='40%'>".$campaigns[$ckey]['activate']."</td>";
			echo "</tr>";
			echo "<tr height='15'>";
			echo "<td width='20%'>".$strClicksBooked.":</td>";
			echo "<td width='20%' align='right'>".($campaigns[$ckey]['clicks'] >= 0 ? $campaigns[$ckey]['clicks'] : $strUnlimited)."</td>";
			echo "<td width='10%'>&nbsp;</td>";
			echo "<td width='20%'>".$strExpirationDate.":</td>";
			echo "<td width='40%'>".$campaigns[$ckey]['expire']."</td>";
			echo "</tr>";
			echo "<tr height='25'>";
			echo "<td width='20%'>".$strConversionsBooked.":</td>";
			echo "<td width='20%' align='right'>".($campaigns[$ckey]['conversions'] >= 0 ? $campaigns[$ckey]['conversions'] : $strUnlimited)."</td>";
			echo "<td width='10%'>&nbsp;</td>";
			echo "<td width='20%'>".$strPriority.":</td>";
			echo "<td width='40%'>".$campaigns[$ckey]['priority']."</td>";
			echo "</tr>";
			echo "</table>";
			echo "<br /></td>";
			echo "</tr>";
		}
		echo "<tr class='break'><td colspan='6' ></td></tr>";

		$i++;
	}
}

echo "<tr height='25'><td colspan='3' height='25' nowrap>";

if ($hideinactive == true) {
	echo "&nbsp;&nbsp;<img src='images/icon-activate.gif' align='absmiddle' border='0'>";
	echo "&nbsp;<a href='advertiser-campaigns.php?clientid=".$clientid."&hideinactive=0'>".$strShowAll."</a>";
	echo "&nbsp;&nbsp;|&nbsp;&nbsp;".$campaignshidden." ".$strInactiveCampaignsHidden;
} else {
	echo "&nbsp;&nbsp;<img src='images/icon-hideinactivate.gif' align='absmiddle' border='0'>";
	echo "&nbsp;<a href='advertiser-campaigns.php?clientid=".$clientid."&hideinactive=1'>".$strHideInactiveCampaigns."</a>";
}

echo "</td>";
echo "<td colspan='3' height='25' align='".$phpAds_TextAlignRight."' nowrap>";
echo "<img src='images/triangle-d.gif' align='absmiddle' border='0'>";
echo "&nbsp;<a href='advertiser-campaigns.php?clientid=".$clientid."&expand=all' accesskey='".$keyExpandAll."'>".$strExpandAll."</a>";
echo "&nbsp;&nbsp;|&nbsp;&nbsp;";
echo "<img src='images/".$phpAds_TextDirection."/triangle-l.gif' align='absmiddle' border='0'>";
echo "&nbsp;<a href='advertiser-campaigns.php?clientid=".$clientid."&expand=none' accesskey='".$keyCollapseAll."'>".$strCollapseAll."</a>&nbsp;&nbsp;";
echo "</td>";
echo "</tr>";

if (!empty($campaigns) && !OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
    echo "<tr class=''><td colspan='6' ></td></tr>";
	echo "<tr height='25'>";
	echo "<td colspan='6' height='25' align='".$phpAds_TextAlignRight."'>";
	echo "<img src='images/icon-recycle.gif' border='0' align='absmiddle'>&nbsp;<a href='campaign-delete.php?clientid=".$clientid."&returnurl=advertiser-campaigns.php'".phpAds_DelConfirm($strConfirmDeleteAllCampaigns).">$strDeleteAllCampaigns</a>&nbsp;&nbsp;";
	echo "</td>";
	echo "</tr>";
}

echo "</table>";
echo "<br /><br />";

/*-------------------------------------------------------*/
/* Store preferences                                     */
/*-------------------------------------------------------*/

$session['prefs']['advertiser-campaigns.php'][$clientid]['hideinactive'] = $hideinactive;
$session['prefs']['advertiser-campaigns.php'][$clientid]['listorder'] = $listorder;
$session['prefs']['advertiser-campaigns.php'][$clientid]['orderdirection'] = $orderdirection;
$session['prefs']['advertiser-campaigns.php'][$clientid]['nodes'] = implode (",", $node_array);

phpAds_SessionDataStore();

/*-------------------------------------------------------*/
/* HTML framework                                        */
/*-------------------------------------------------------*/

phpAds_PageFooter();

?>
