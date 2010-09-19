<?php	

require_once ('WikifarmDriver.php');
require_once('classTextile.php');

class WikifarmPageMachine extends WikifarmDriver {
	public $tabNames, $js_tabNames, $textile;

	function __construct($db = null) {
		WikifarmDriver::__construct($db);
		$this->textile = new Textile;
	}

	function page_help() {
		return $this->textile->textileThis(file_get_contents("help.textile")).<<<BLOCK
<script type='text/javascript'>
$("#Help h2").before('<div class="clear1em" />');
$("#Help h2").wrap('<div class="ui-widget ui-state-highlight ui-corner-all wf-message-box" />');
$("#Help h2").wrap('<p />');
$("#Help h2").before('<span class="ui-icon ui-icon-pin-s wf-message-icon" />');
$("#Help h2").replaceWith(function(){\$(this).parent().attr('id',\$(this).attr('id')); return '<strong>'+\$(this).html()+'</strong>';});
$("#Help img").after('<br clear="all" />');
$("#Help img").wrap('<div style="float:left;" />');
$("#Help img").wrap('<div class="ui-widget ui-state-highlight ui-corner-all" style="padding: 10px" />');
$("#Help li").css("padding-bottom", "0.5em");
</script>
BLOCK;
	}

	function page_debug() {
		$output = "";
		$output .= <<<BLOCK
<h3>ajax tests</h3>
<FORM id="fooform"><INPUT type="text" name="sample_id" value="sample" /></FORM>
<P>test_success: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_success">Test success</button></P>
<P>test_failure: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_failure">Test failure</button></P>
<P>test_ajax_error: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_ajax_error">Test ajax error</button></P>
<P>test_alert: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_alert">Test alert</button></P>
<P>test_alert_redirect: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_alert_redirect">Test alert-and-redirect</button></P>
<P>test_selecttab: <button class="generic_ajax" ga_form_id="fooform" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_selecttab">Test selecttab</button></P>
<P>test_activated: <button class="generic_ajax" ga_loader_id="fooloader" ga_message_id="foomessage" ga_action="test_activated">Make sure my account is activated</button></P>
<div style="min-height:40px"><div id="fooloader" class="ui-helper-hidden" /><div id="foomessage" class="ui-helper-hidden" /></div>
<h3>current sqlite schema</h3>
<pre>
BLOCK;
		$result = $this->query( "SELECT sql FROM sqlite_master" );
		foreach ($result as $row) { 
			$output .= htmlspecialchars($row['sql']) . "\n\n";
		}
		$output .= "</pre>";
		$output .= "<h3>Contents of _cache</h3><br>(it might show up if there was something in it...)<pre>" . $this->uglydumpling($this->cacheDebugDump()) . "</pre>";		
		return $output;
	}

	// all about the tabs	
	function tabGet($tab) {
		if (!method_exists ($this, "page_$tab"))
			return __METHOD__.": Invalid page request: \"$tab\"";
		return call_user_func (array ($this, "page_$tab"));
	}

	function page_myaccount($userid = false) {
		$admin_mode = false;
		$uid = '';
		if ($userid && $this->is_a_user($userid) && $this->_security('admin')) {
			$admin_mode = true;
			$this->Focus($userid);
			$uid = md5($this->openid);
		}
		$q_openid = $this->openid;
		$q_email = htmlspecialchars($this->getUserEmail());
		$q_realname = htmlspecialchars($this->getUserRealname());
		$q_mwusername = htmlspecialchars($this->getMWUsername());
		$q_uota = $this->getWikiQuota();
		$q_uota = $admin_mode ? "<input type=\"text\" name=\"quota\" size=\"4\" maxlength=\"3\" value=\"$q_uota\" />" : $q_uota;
		$icon = "info";
		$your = $admin_mode ? "this user's" : "your";
		$Your = ucfirst ($your);
		$you = $admin_mode ? "this user" : "you";
		$You = ucfirst ($you);
		if (!$this->getUserEmail() || !$this->getUserRealname()) {
			$icon = "circle-arrow-e";
			$activation_status = "Please provide $your real name and email address.";
		}
		else if ($this->isActivated()) {
			$icon = "circle-check";
			$activation_status = "$Your account is active.";
		}
		else if ($admin_mode) {
			$icon = "alert";
			$activation_status = "This account has not been activated.";
		}
		else if ($this->isActivationRequested())
			$activation_status = "Your account has not yet been activated by a site administrator.  You can update your personal information and request more group memberships, but you cannot view or create any wikis until your account is activated.";
		else {
			$icon = "circle-arrow-e";
			$activation_status = "If the information on this page is correct, please <a href=\"/?tabActive=groups\">select your group affiliations and request account activation</a>.";
		}
		$preferences = "";
		foreach ($this->getUserPrefs() as $p) {
			if ($preferences) $preferences .= "<br />";
			extract ($p);
			$checked = $value ? "checked" : "";
			if ($type == "checkbox")
				$preferences .= "<input type=\"checkbox\" name=\"pref_$prefid\" value=\"1\" $checked /> $description";
		}
		if ($admin_mode) $this->Focus(); // return to admin
		return <<<BLOCK
<div class="ui-widget ui-state-highlight ui-corner-all wf-message-box"><p><span class="ui-icon wf-message-icon ui-icon-$icon" />$activation_status</p></div>
<div class="clear1em" />
<form id="maf{$uid}">
<input type="hidden" name="userid" value="$q_openid">
<table>
<thead></thead><tbody>
<tr>
<td class="minwidth formlabelleft">OpenID</td><td>$q_openid</td>
</tr><tr>
<td class="minwidth formlabelleft">Wiki&nbsp;quota</td><td>$q_uota</td>
</tr><tr>
<td class="minwidth formlabelleft">Email&nbsp;address</td><td><input type="text" name="email" value="$q_email" /></td>
</tr><tr>
<td class="minwidth formlabelleft">Real&nbsp;name</td><td><input type="text" name="realname" value="$q_realname" /></td>
</tr><tr>
<td class="minwidth formlabelleft">Preferred&nbsp;MediaWiki&nbsp;username</td><td><input type="text" name="mwusername" value="$q_mwusername" /></td>
</tr><tr>
<td class="minwidth formlabelleft">Preferences</td><td>$preferences</td>
</tr><tr>
<td class="minwidth formlabelleft"></td><td><button class="generic_ajax" ga_form_id="maf{$uid}" ga_action="myaccount_save" ga_message_id="myaccount_message" ga_loader_id="myaccount_loader">Save changes</button><span id="myaccount_loader" class="ui-helper-hidden"></span></td>
</tr></tbody></table>
<div id="myaccount_message" class="ui-helper-hidden" />
</form>
BLOCK;
	}

// All Wikis Tab - Show a fancy list of all available wikis on the site
	function page_wikis() {
		if (!$this->isActivated()) {			
			error_log ("page_wikis: requested by unactivated user");
			return "";
		}
		$wikiArray = $this->getAllWikis();
/* --- Javascript and CSS --- */		
		$output = <<<BLOCK
<script type='text/javascript'>
$.fn.dataTableExt.afnFiltering.push (function(oSettings,aData,iDataIndex) {
	var nTr = oSettings.aoData[iDataIndex].nTr; 
	if (nTr.className.match(/nonreadable/) && $('#viewreadableselected').attr('checked'))
	    return false;
	if (nTr.className.match(/nonwritable/) && $('#viewwritableselected').attr('checked'))
	    return false;
	return true;
});
$(function() {
	$('#viewallradio').buttonset();
	var oTable = $('#allwikis').dataTable({'bJQueryUI': true, 'iDisplayLength': 100, 'aoColumnDefs': [ { 'bSearchable': false, 'aTargets': [ 2, 5, 6, 7 ] } ] });
	$('#viewallradio input').change( function(){ oTable.fnDraw(); } );
	$('.editbutton').click(function(){ mywikisLoadTabOnce = $(this).attr('wikiname'); wf_tab_select('tabs', 'mywikis'); });
	$('.linkbutton').click(function(){ var url = $(this).attr('link'); $(location).attr('href',url); })
	$('.loginselect').change( function() { if ($(this).val()!='') { $(this).addClass('generic_ajax'); $(this).click(); $(this).removeClass('generic_ajax'); } $(this).val(''); return false; } );
	\$('#allwikis a[icon]').each(function(){\$(this).button({icons:{primary:\$(this).attr('icon')}});});
	\$('#allwikis a.ui-helper-hidden[icon]').hide();
});
</script>
<style type="text/css">
#allwikis tr { min-height: 24px; }
#allwikis td { padding: 0px 5px; }
</style>
BLOCK;
		$output .= $this->frag_request_access();
		if ($this->isAdmin()) $output .= $this->frag_admin_managewiki();
		/* --- Page Heading --- */
		$output .= "<table><tr><td><div class=\"ui-widget ui-state-highlight ui-corner-all wf-message-box\"><p><span class=\"ui-icon wf-message-icon ui-icon-folder-collapsed\" /><strong>All Wikis:</strong> browse a list of all wikis on this site, or request access to specific wikis.</p></div><div class=\"clear1em\" /></td>\n".
			"<td><div align=right id='viewallradio'>\n".
				"\t<input type='radio' id='viewallselected' name='viewallradio' checked='checked' /><label for='viewallselected'>View All</label>\n".
				"\t<input type='radio' id='viewreadableselected' name='viewallradio' /><label for='viewreadableselected'>View Readable</label>\n".
				"\t<input type='radio' id='viewwritableselected' name='viewallradio' /><label for='viewwritableselected'>View Writable</label>\n".				
			"</div></td></tr></table>\n".
			"<form id='allwikisform'>\n" .
			"<table id='allwikis'>\n" .
			"<thead><tr>\n".
				"<th class='minwidth'>#</th>".
				"<th class='minwidth'>Wiki</th>".
				"<th class='minwidth'>&nbsp;</th>".
				"<th>Owner</th>".
				"<th class='minwidth'>Group(s)</th>".
				"<th class='minwidth'>Manage</th>".
				"<th class='minwidth'>View/edit</th>".
				"<th class='minwidth'>Request</th>".
			"</tr></thead>\n<tbody>\n";
/* --- Each Wiki Listing --- */	
		foreach ($wikiArray as $row) {
			extract ($row);
			$requested_writable = $requested_autologin;
			$writable = !!($autologin && $autologin[0]);
			if ($realname == '')
				$realname = $wikiname;
			$q_realname = htmlspecialchars($realname);
			$show_edit = ($this->openid == $owner_userid && !$this->isAdmin() ? '' : 'ui-helper-hidden');
			$show_admin_edit = ($this->isAdmin() ? '' : 'ui-helper-hidden');
			$output .= "\t<tr class='" .($this->openid == $owner_userid ? 'mine ' : '') . (!$readable ? 'nonreadable ' : '') . (!$writable ? 'nonwritable' : '') . "'>".
				"<td class='minwidth nowrap' style='text-align:right'>$wikiid</td>".
				"<td class='minwidth nowrap'>".($readable ? "<a href=\"/$wikiname/\">$wikiname</a>" : $wikiname)."</td>".
				"<td class='minwidth nowrap'>".($writable ? "<span class='ui-icon ui-icon-pencil' style='float:right; vertical-align:bottom;'></span>" : "" )."</td>".
				"<td class='minwidth nowrap'>".htmlspecialchars($owner_realname).
				"</td><td>".(implode(", ", $groups)).
				"</td><td class='minwidth nowrap'>".
				"<a icon='ui-icon-gear' id='button-admin-$wikiid' class='editbutton $show_edit' wikiname='$wikiname' wikititle=\"$q_realname\">Manage</a>" .
				"<a icon='ui-icon-gear' class='admin-manage-button $show_admin_edit' wikiid='$wikiid'>Admin</a>".
				"</td>";
	/* --- The Increasingly-Complicated Button Bar --- */
			$output .= "<td class='minwidth nowrap'>";
			// these are prepared in a way that we can use as little or as much Ajax as we like.
			$show_login = ($autologin[0] ? '' : 'ui-helper-hidden');
			$show_view = (!$writable && $readable ? '' : 'ui-helper-hidden');
			$show_requestpending = ($requested_writable || $requested_readable ? '' : 'ui-helper-hidden');
			$show_requestwrite = (!$writable && $readable && !$requested_readable && !$requested_writable ? '' : 'ui-helper-hidden');
			$show_request =  (!$writable && !$readable ? '' : 'ui-helper-hidden');
			$output .= "<select id='loginselect-$wikiid' name='loginselect-$wikiid' wikiid='$wikiid' class='wf-button loginselect $show_login' ga_form_id='allwikisform' ga_action='loginas'><option value=''>Login as...</option>";
			if ($autologin[0]) foreach ($autologin as $alogin) { $output .= "<option value='$alogin'>$alogin</option>"; }
			$output .= "<option value='0'>Manual sign-in</option></select>" .
				"<a icon='ui-icon-play' id='button-viewwiki-$wikiid' class='linkbutton $show_view' link='/$wikiname/'>View</a>" .
				"</td><td class='minwidth nowrap'>" .
				"<div id='button-requestpending-$wikiid' class='ui-widget ui-button-text-icon-primary ui-state-disabled $show_requestpending' style='position:relative'><span class='ui-button-icon-primary ui-icon ui-icon-clock'></span><span class='ui-button-text'>Request pending</span></div>" .
				"<a icon='ui-icon-key' id='button-requestwrite-$wikiid' class='requestbutton $show_requestwrite' wikiid='$wikiid' wikititle=\"$q_realname\" wikiname='$wikiname' writeonly='true'>Request&nbsp;write&nbsp;access</a>" .
				"<a icon='ui-icon-key' id='button-request-$wikiid' class='requestbutton $show_request' wikiid='$wikiid' wikititle=\"$q_realname\">Request access</a>" .
				"</td></tr>\n";
		}
		$output .= "</tbody></table></form>\n";
		return $output;
	}

	function page_mywikis() {
		if (!$this->isActivated()) {
			error_log (__METHOD__.": requested by unactivated user");
			return false;
		}
		$wikiArray = $this->getMyWikis();
		$content = "";
		$tabs = "";
		foreach ($wikiArray as $row) {
			extract ($row);
			$visible_to = implode(", ", $groups);
			$q_realname = htmlspecialchars ($realname);
			$tabs .= "\t\t<li><a tab_id='tab_$wikiname' href=\"#tab_$wikiname\"><span class=\"ui-icon ui-icon-triangle-1-e wf-button-icon\" /> <u>$wikiname</u>: $q_realname</a></li>\n";
			$content .= "<div id=\"tab_$wikiname\">" .	$this->frag_managewiki ($row) .	"</div>\n";
		}
		$groups_options = "";
		foreach ($this->getAllGroups() as $g) {
			$groupid = htmlspecialchars($g["groupid"]);
			if ($groupid == "ADMIN")
				continue;
			if ($groupid == "users")
				$groupname = "Everyone";
			else
				$groupname = htmlspecialchars($g["groupname"]);
			$groups_options .= "<option value=\"$groupid\">$groupname</option>";
		}
		$q_mwusername = htmlspecialchars($this->getMWUsername());
		$grantedit = $this->frag_grant_edit();
		return <<<BLOCK
<div class="ui-widget ui-state-highlight ui-corner-all wf-message-box"><p><span class="ui-icon wf-message-icon ui-icon-wrench" />Manage your wikis: invite users, download database backups, view web stats.</p></div><div class="clear1em" />
<script language="JavaScript">
$(function() {
	$('#mywikistabs').tabs({show: function(event,ui){window.location.hash="";}});
	if (mywikisLoadTabOnce != '') {
		selectTabByName ('#mywikistabs','tab_'+mywikisLoadTabOnce);
		mywikisLoadTabOnce = '';
	}		
});
</script>
<div id="mywikistabs">
	<ul>
{$tabs}
		<li><a href="#newwikitab"><span class="ui-icon ui-icon-arrowreturnthick-1-s" style="float: left; margin-right: .3em;"></span>Create a new wiki</a></li>
	</ul>
{$content}
<div id="newwikitab">
<form id="createwikiform" action="#">
<table>

<tr><td class="formlabelleft nowrap">Wiki title:</td>
<td class="minwidth"><input type=text name=realname size=32 value="Lab Notebook"></td>
<td>Full title of your wiki</td>
</tr>

<tr><td class="formlabelleft nowrap">Wiki name: </td>
<td class="minwidth"><input type=text name=wikiname size=32 maxlength=12></td>
<td class="celltexttoppad">3 to 12 lower case letters.<br />your wiki will be http://{$_SERVER['HTTP_HOST']}/name</td>
</tr>

<tr><td class="formlabelleft nowrap celltexttoppad">Your username in the new wiki: </td>
<td class="minwidth"><input type=text name=mwusername size=32 value="{$q_mwusername}"></td>
<td class="celltexttoppad">letters and digits only.  start with an upper case letter.
</tr>

<tr><td class="formlabelleft nowrap celltexttoppad">Groups to invite to the new wiki: </td>
<td class="minwidth"><select multiple name="groups[]">$groups_options</select></td>
<td class="celltexttoppad">control-click to select and de-select multiple groups
</tr>

<tr><td></td>
<td class="minwidth"><button class="generic_ajax" ga_form_id="createwikiform" ga_loader_id="createwiki_loader" ga_message_id="createwiki_message" ga_action="createwiki">Create new wiki</button></td>
<td></td>
</tr>
</table>

<div style="min-height:40px"><div id="createwiki_loader" /><div id="createwiki_message" /></div>

</form>
</div>
</div>
{$grantedit}
BLOCK;
	}

	function page_groups($userid=false) {
		$admin_mode = false;
		$uid = '';
		if ($userid &&
		    $this->is_a_user($userid) &&
		    $this->_security('admin')) {
			$admin_mode = true;
			$this->Focus($userid);
			$uid = md5($this->openid);
		}
		$q_openid = $this->q_openid;
/* --- groups: page frills --- */
		$request_activation = '';
		$hidden_claim_dialog = '';
		$claim_alert = '';
		$hidden_uid_input = '';
		if (!$admin_mode) {
			if (!$this->isActivated() && !$this->isActivationRequested()) {
				$request_activation = $this->textHighlight("<p>Please select any groups your account should belong to, then click the \"submit\" button.  Your account will have to be activated by a site administrator before you can create, view, or edit any wikis.</p>") .
					"<input type=hidden name=\"group_request[]\" value=\"users\" />";
			}
			$claim_alert = $this->textHighlight ("If you had a username and password on the pub.med server, enter them here to regain access to your wiki and group memberships.<blockquote><button class='claimaccountbutton'>Claim pre-OpenID account</button></blockquote>");
			$hidden_claim_dialog = $this->frag_claim_account();
			$explanation_alert = $this->textHighlight ("This page shows which groups your account belongs to.  You can also request to be added to more groups (your request will be approved by a site administrator)." );
			$request_button = "<button id='group_request_submit' class='generic_ajax' ga_form_id='group_request' ga_action='requestgroups' ga_loader_id='group_request_loader' disabled>Submit request</button>";
		} else { // Admin stuff
			$explanation_alert = $this->textHighlight ("Editing group memberships for $q_openid");
			$request_button = "<button id='group_request_submit' class='generic_ajax' ga_form_id='group_request{$uid}' ga_action='setgroups' ga_loader_id='group_request_loader' ga_message_id='group_request_message' admin_mode='1'>Save changes</button>";
			$hidden_uid_input = "<input type='hidden' name='userid' value='".htmlspecialchars($this->openid)."' />";
		}
/* --- groups: output page head --- */
		$output = <<<BLOCK
{$explanation_alert}
<form id="group_request{$uid}">
{$request_activation}
{$hidden_uid_input}
<div id="grouplistcontainer{$uid}">
<table id="grouplist{$uid}">
<thead><tr>
<th class="minwidth">&nbsp;</th>
<th class="minwidth">Group</th>
<th>&nbsp;</th>
</tr></thead>
<tbody>
BLOCK;

/* --- groups: table body ---- */
		foreach ($this->getAllGroups($admin_mode) as $g) {
			if (($g["groupid"] == "ADMIN" || $g["groupid"] == "users") && !$admin_mode)
				continue;
			$groupid = htmlspecialchars($g["groupid"]);
			$attrs = "";
			if ($g["member"]) $attrs = "checked";
			if ($g["requested"] && !$admin_mode) $attrs = "checked";
			if ($attrs && !$admin_mode) $attrs .= " disabled";
			$extra = "";
			if ($g["requested"]) $extra = "(request&nbsp;pending)";
			$output .= <<<BLOCK
<tr>
<td class="minwidth"><input type="checkbox" name="group_request[]" value="$groupid" $attrs/></td>
<td class="minwidth">$groupid</td>
<td>$extra</td>
</tr>
BLOCK;
		}
/* --- groups: tail --- */ 
$output .= <<<BLOCK
</tbody>
</table>
</div>
<p>
{$request_button} after selecting groups.
<span id="group_request_loader"></span></p>
<div id="group_request_message" class="ui-helper-hidden" />
</form>
{$claim_alert}
{$hidden_claim_dialog}

<script language="JavaScript">
	$(function(){
		$("#grouplist{$uid}").mutateID().dataTable({ 'bJQueryUI': true, "bPaginate": false, "bSort": false, "bInfo": false, "bFilter": false});
		group_request_enable();
	});
</script>
<br clear />
BLOCK;
		if ($admin_mode) $this->Focus(); // return to admin
		return $output;
	}
/* --- END: groups ---*/

/* --- function page_users() --- */
	function page_users() {
		if (!$this->isActivated()) return "";
		$adminrow = ($this->isAdmin() ? "\n<th style='width: 30'>Admin</th>" : '');
		$html = <<<BLOCK
<table id="userlist">
<thead>
<tr>{$adminrow}
<th>Email</th>
<th>Real Name</th>
<th>Preferred MW Username</th>
<th>ID</th>
</tr>
</thead>
<tbody>
BLOCK;
		$userlist = $this->getAllActivatedUsers();
		if ($this->isAdmin())
			$userlist = array_merge ($userlist, $this->getAllUnactivatedUsers());
		foreach ($userlist as $u) {
			foreach ($u as $k => $v) { $u["q_$k"] = htmlspecialchars($v); }
			extract ($u);
			if ($this->isAdmin()) $adminrow = "\n<td><button class='admin-user-button' userid='$q_userid'><span class='ui-icon ui-icon-wrench'></span></button></td>";
			$html .= <<<BLOCK
<tr>{$adminrow}
<td>$q_email</td>
<td>$q_realname</td>
<td>$q_mwusername</td>
<td>$q_userid</td>
</tr>
BLOCK;
		}
		if ($this->isAdmin()) $adminrow = $this->frag_admin_manageuser();
		$html .= <<<BLOCK
</tbody>
</table>
{$adminrow}
<script language="JavaScript">
$("#userlist").dataTable({'bJQueryUI': true, "iDisplayLength": 25, "bLengthChange": false});
</script>
BLOCK;
		return $html;
	}

	function page_tools() {
		return <<<BLOCK
<p><a href="table.php">Excel -> Wiki Table converter</a></p>
BLOCK;
	}

	function page_requests() {
		$requests = $this->getAllRequests();
		$num = count($requests);
		$html = $this->textHighlight("You have <strong>$num</strong> pending request". ($num == 1 ? "." : "s.") );

		$html .= <<<BLOCK
<table id="myreqs">
<thead>
<tr><th>&nbsp;</th><th>&nbsp;</th><th>Request</th><th>Name</th><th>Email</th><th>OpenID</th></tr>
</thead>
<tbody>
BLOCK;
		foreach ($requests as $req) {
			$email = "";
			$wikiname = null;
			$mwusername = null;
			extract ($req);
			$q_wikiname = htmlspecialchars(isset($wikiname) ? $wikiname : "");
			$q_mwusername = htmlspecialchars(isset($mwusername) ? $mwusername : "");
			$q_groupname = htmlspecialchars(isset($groupname) ? $groupname : "");
			if (!$wikiid && $groupname == "users")
				$request = "Activate account";
			else if (!$wikiid)
				$request = "Join \"$q_groupname\" group";
			else if ($mwusername)
				$request = "Edit <a href=\"/$q_wikiname/\">$q_wikiname</a> as \"$q_mwusername\"";
			else
				$request = "View <a href=\"/$q_wikiname/\">$q_wikiname</a>";

			$q_name = htmlspecialchars($realname);
			$q_email = htmlspecialchars($email);
			$q_openid = htmlspecialchars($userid);
			$html .= <<<BLOCK
<tr id="req_row_$requestid">
<td><button class="req_response_button approve" requestid="$requestid">Approve</button></td>
<td><button class="req_response_button reject" requestid="$requestid">Reject</button></td>
<td requestid="$requestid">$request</td>
<td>$q_name</td>
<td>$q_email</td>
<td>$q_openid</td>
</tr>
BLOCK;
		}
		$html .= <<<BLOCK
</tbody></table>
<script language="JavaScript">
$("#myreqs").dataTable({'bJQueryUI': true, "bInfo": false, "bPaginate": false, "aaSorting": [[5,"asc"],[2,"asc"]]});
</script>
<br clear />
BLOCK;
		
		return $html;
	}
	
	function uglydumpling ($x) {
		return "<pre>".htmlspecialchars(print_r($x,true))."</pre>";
	}

	function frag_managewiki ($wiki) {
		extract ($wiki);
		$wikiid = sprintf ("%02d", $wikiid);
		$html = ""; // ~jer added a button here
		$html .= <<<BLOCK
<div style="float: right;">
<a class="managebutton{$wikiid}" href="?view=$wikiid">View wiki</a>
<a class="managebutton{$wikiid}" href="/$wikiid/private/wikidb$wikiid.sql.gz">Download backup</a>
<a class="managebutton{$wikiid}" href="/$wikiid/private/stats/awstats.$wikiid.html">Web stats</a>
<a class="managebutton{$wikiid}" href="/$wikiid/private/access_log.txt">Raw access log</a>
</div>
<script language="JavaScript">
$(".managebutton{$wikiid}").button({icons:{primary:'ui-icon-zoomin'}})
	.first().button({icons:{primary:'ui-icon-play'}})	
	.next().button({icons:{primary:'ui-icon-suitcase'}});
</script>
<div class="clear1em" />
BLOCK;
		$html .= "<form id=\"mwf$wikiid\">";
		$html .= "<input type=\"hidden\" name=\"wikiid\" value=\"$wikiid\" />\n";
		$html .= $this->textHighlight ("All members of these groups can <strong>view</strong> the <a href=\"/$wikiname/\">$wikiname</a> wiki.", "person");
		$html .= "<table id=\"mwg${wikiid}\">";
		$html .= "<thead><tr><th class=\"minwidth\">&nbsp;</th><th>&nbsp;</th></tr></thead><tbody>";
		foreach ($this->getAllGroups() as $g) {
			if ($g["groupid"] == "ADMIN") continue;
			$html .= "<tr>";
			$checked = false === array_search ($g["groupid"], $groups) ? "" : "checked";
			$groupid = $g["groupid"];
			$html .= "<td class=\"minwidth\"><input type=\"checkbox\" class=\"generic_ajax\" ga_form_id=\"mwf$wikiid\" ga_action=\"managewiki_groups\" id=\"mw${wikiid}_group_".htmlspecialchars($g["groupid"])."\" name=\"mw${wikiid}_groups[]\" value=\"".htmlspecialchars($g["groupid"])."\" $checked></td>";
			$html .= "<td>".htmlspecialchars($g["groupname"])."</td>";
			$html .= "</tr>";
		}
		$html .= "</tbody></table>";

		$html .= "<div class=\"clear1em\" />";

		$invited_users = $this->getInvitedUsers ($wikiid);
		$invited_userid = array();
		$invited_userid_w = array();
		foreach ($invited_users as $u) {
			$invited_userid[$u["userid"]] = true;
			if ($u["mwusername"])
				$invited_userid_w[$u["userid"]] = true;
			else if ($u["read_via_group"])
				$invited_userid_via_group[$u["userid"]] = true;
		}
		$html .= $this->textHighlight ("You can also invite individual users to <strong>view</strong> and <strong>edit</strong> the <a href=\"/$wikiname/\">$wikiname</a> wiki.", "person");
		$html .= "<table id=\"mwu${wikiid}\">";
		$html .= "<thead><tr><th class=\"minwidth\"></th><th></th><th>&nbsp;</th></tr></thead><tbody>";
		foreach ($this->getAllActivatedUsers() as $u) {
			$html .= "<tr>";
			$checked = isset ($invited_userid[$u["userid"]]) ? "checked" : "";
			$disabled = isset ($invited_userid_via_group[$u["userid"]]) ? "disabled" : "";
			$html .= "<td class=\"minwidth nowrap\"><input type=\"checkbox\" class=\"generic_ajax\" ga_form_id=\"mwf$wikiid\" ga_action=\"managewiki_users\" id=\"mw${wikiid}_userview_".md5($u["userid"])."\" name=\"mw${wikiid}_userview_".md5($u["userid"])."\" value=\"".htmlspecialchars($u["userid"])."\" $checked $disabled />view</td>";

			$checked = isset ($invited_userid_w[$u["userid"]]) ? "checked" : "";
			$html .= "<td class=\"minwidth nowrap\"><input type=\"checkbox\" class=\"granteditbutton\" wikiid=\"".$wikiid
				."\" wikiname=\"".htmlspecialchars($wikiname)
				."\" wikititle=\"".htmlspecialchars($realname)
				."\" realname=\"".htmlspecialchars($u["realname"])
				."\" email=\"".htmlspecialchars($u["email"])
				."\" userid=\"".htmlspecialchars($u["userid"])
				."\" mwusername=\"".htmlspecialchars($u["mwusername"])
				."\" id=\"mw${wikiid}_useredit_".md5($u["userid"])
				."\" value=\"1\" $checked />edit&nbsp;&nbsp;&nbsp;&nbsp;</td>";
			$comma_email = $u["email"] ? ", ".$u["email"] : "";
			$html .= "<td>".htmlspecialchars($u["realname"].$comma_email." (".$u["userid"].")")."</td>";
			$html .= "</tr>";
		}
		$html .= "</tbody></table>";

		$html .= "<br /><div style=\"min-height: 12px;\" /><br />";

		$html .= "</form>";
		$html .= "<script language=\"JavaScript\">
\$(\"#mwg$wikiid\").mutateID().dataTable({'bJQueryUI': true, \"bAutoWidth\": false, \"bInfo\": false, \"bSort\": false, \"bFilter\": false, \"bLengthChange\": false, \"bPaginate\": false});
\$(\"#mwu$wikiid\").mutateID().dataTable({'bJQueryUI': true, \"bAutoWidth\": false, \"bInfo\": false, \"bSort\": false, \"bLengthChange\": false});
</script>\n";
		return $html;
	}

// ajax loaded dialog box content
	function page_admin_managewiki() {
		if (!$this->isAdmin()) {
			error_log (__METHOD__.": requested by non-admin user");
			return page_adminonly();
		}
		$wiki = $this->getWiki($_GET['wikiid'] + 0);
		if (!is_array($wiki)) {
			error_log (__METHOD__.": invalid wikiid in GET");
			return "Invalid ID = " . $_GET['wikiid'];
		}				
		return $this->frag_managewiki($wiki);
	}		

// needs a <button class='admin-manage-button' wikiid='n'>Manage Wiki</button>
	function frag_admin_managewiki() {
		return <<<BLOCK
<script type="text/javascript">
	$(function() {
		$('#amw-dialog')
		.dialog({ modal: true, autoOpen: false, width: 800, position: ['center', 32] });
		$('.admin-manage-button').click(function(){
			var id = $(this).attr('wikiid');
			$('#amw-content').hide();
			$('#amw-content').load('?tab=admin_managewiki&wikiid='+id, function() {
				$('#amw-waiting').hide();
				$('#amw-content').show();
			});
			$('#amw-content').hide();
			$('#amw-waiting').css('line-height', '400px').show();
			$('#amw-dialog').dialog('open');
			return false;
		});
		
		$(".managebutton").button({icons:{primary:'ui-icon-zoomin'}});
		$(".managebutton:first").button({icons:{primary:'ui-icon-suitcase'}});
	});
$('#reqwriteaccess').live('click', function(){ if(!$('#reqwriteaccess').attr('disabled')) $('#reqmwusername').attr('disabled',!$('#reqwriteaccess').attr('checked')); });
</script>

<div id="amw-dialog" title="Admin: Manage A Wiki" class="wf-dialog">
	<div id="amw-content"></div>
	<div id="amw-waiting" style="width: 100%; line-height: 150px; text-align: center;">Loading...</div>
</div>
BLOCK;
	}

// ajax loaded dialog box content 
	function page_admin_manageuser() {
		if (!$this->_security( array('access'=>'admin' ))) return page_adminonly();
		$user = $this->getUser($_GET['userid']);
		if (!is_array($user)) {
			error_log (__METHOD__.": invalid userid in GET");
			return "Invalid UserID = " . $_GET['userid'];
		}
		$useraccount = $this->page_myaccount($user['userid']);
		$usergroups = $this->page_groups($user['userid']);
		return <<<BLOCK
<script type="text/javascript">
	$(function() {
		$('#amu-tabs').tabs();
	});
</script>
<div id='amu-tabs'>
	<ul>
		<li><a tab_id='admin-userinfo-tab' href="#admin-userinfo-tab">User Details</a></li>
		<li><a tab_id='admin-usergroups-tab' href="#admin-usergroups-tab">Groups</a></li>
	</ul>
	<div id='admin-userinfo-tab'>{$useraccount}</div>
	<div id='admin-usergroups-tab'>{$usergroups}</div>
</div>	
BLOCK;
	}

// needs a <button class='admin-user-button' userid='n'>Manage User</button>
	function frag_admin_manageuser() {
		return <<<BLOCK
<script type="text/javascript">
	$(function() { 
		$('#amu-dialog')
			.attr('title','Admin: Modify User')
			.dialog({ modal: true, autoOpen: false, width: 800, buttons: { 
				"Close": function() { $(this).dialog("close"); }
			} });
		$('.admin-user-button').click(function(){
			var id = $(this).attr('userid');
			$('#amu-content').load('?tab=admin_manageuser&userid='+encodeURIComponent(id), function() {
				$('#amu-waiting').hide();
				$('#amu-content').show();
			});			
			$('#amu-content').hide();
			$('#amu-waiting').css('line-height', '400px').show();
			$('#amu-dialog').dialog('open');
			return false;
		});
	});
$('#reqwriteaccess').live('click', function(){ if(!$('#reqwriteaccess').attr('disabled')) $('#reqmwusername').attr('disabled',!$('#reqwriteaccess').attr('checked')); });
</script>

<div id="amu-dialog" class="wf-dialog">
	<div id="amu-content"></div>
	<div id="amu-waiting" style="width: 100%; line-height: 150px; text-align: center;">Loading...</div>
</div>
BLOCK;
	}
	
	// the access denied banner
	function page_adminonly() {
		return textError("<strong>Access Restricted</strong> Sorry, your account does not not permit the action you have requested.");
	}	

	function textHighlight ($text, $icon="info", $id=false) {
		$idattr = $id === false ? "" : "id=\"".htmlspecialchars($id)."\"";
		$html = '<div class="ui-widget" '.$idattr.'><div class="ui-state-highlight ui-corner-all wf-message-box"><p>';
		if ($icon)
			$html .= '<span class="ui-icon ui-icon-'.$icon.' wf-message-icon" />';
		$html .= $text;
		$html .= '</p></div></div>';
		return $html;
	}

	function textError($text, $icon="alert") {
		$html = $this->textHighlight ($text, $icon);
		return str_replace ('ui-state-highlight', 'ui-state-error', $html);
	}
	
	// Request access to a wiki, served in a popup.
	function frag_request_access() {
		$q_defaultmwusername = htmlspecialchars ($this->getMWUsername());
		$footnote = $this->textHighlight("<strong>Note:</strong> If you already have an account on this wiki, you do not need to request access.  Just log in once using the <a href=\"#\" id=\"reqspeciallogin\">MediaWiki login page</a> to associate your wiki account with your OpenID.", "info", "reqnativeloginhint");

		$output = <<<BLOCK
<script type="text/javascript">
	$(function() { 
		$('#getaccessdialog')
			.attr("ga_message_id", "requestmessage")
			.dialog({ modal: true, autoOpen: false, width: 400, buttons: { 
				"Send Request": function() { dialog_submit(this, "#getaccess"); }, 
				"Cancel": function() { $(this).dialog("close"); }
			} });
		$('.requestbutton').click(function(){
			$('#requestmessage').hide();
			$('#reqwikiname').html('<strong>'+$(this).attr('wikititle')+'</strong>');
			$('#reqspeciallogin').attr('href','/'+$(this).attr('wikiname')+'/Special:Userlogin');
			$('#reqwriteaccess').attr('checked',true).removeAttr('disabled');
			$('#reqmwusername').val('$q_defaultmwusername').removeAttr('disabled');
			$('#reqwikiid').val($(this).attr('wikiid'));
			if ($(this).attr('writeonly')) {
				$('#reqwriteaccess').attr('disabled','disabled');
				$('#reqnativeloginhint').show();
			}
			else
				$('#reqnativeloginhint').hide();
			$('#getaccessdialog').dialog('open');
			return false;
		});
	});
$('#reqwriteaccess').live('click', function(){ if(!$('#reqwriteaccess').attr('disabled')) $('#reqmwusername').attr('disabled',!$('#reqwriteaccess').attr('checked')); });
</script>

<div id="getaccessdialog" title="Request access to a wiki" class="wf-dialog">
<form id="getaccess">
<table>
	<tr><td class="formlabelleft">Wiki name:</td><td id="reqwikiname">&nbsp;</td></tr>
	<tr><td class="formlabelleft">Write access wanted?</td><td><input type=checkbox id="reqwriteaccess" name="writeaccess" value="true" checked="checked">&nbsp;</td></tr>
	<tr><td class="formlabelleft">Username you want:</td><td><input type="text" id="reqmwusername" name="mwusername"></td></tr>
</table>
<input type="hidden" name="wikiid" id="reqwikiid" value=" ">
<input type="hidden" name="ga_action" value="requestwiki">
</form>
<div class="ui-widget" id="requestmessage">
	<div class="ui-state-highlight ui-corner-all wf-message-box ui-helper-hidden"></div>
</div>
{$footnote}
</div>
BLOCK;
		return $output;
	}

	// Grant access to a wiki, served in a popup.
	function frag_grant_edit() {
		return <<<EOT
<script type="text/javascript">
	$(function() {
		$('#granteditdialog')
			.attr('title','Invite user to edit your wiki')
			.attr("ga_message_id", "grantmessage")
			.dialog
		({ modal: true,
			 autoOpen: false,
			 width: 400,
			 buttons: { "OK": function() { dialog_submit(this, "#granteditform"); },
				    "Cancel": function() { $(this).dialog("close"); } }
		});
		$('.granteditbutton').click(function(){
			$('#grantmessage').hide();
			$('#grantwikiname').html($(this).attr('wikiname'));
			$('#grantwikititle').html($(this).attr('wikititle'));
			$('#grantrealname').html($(this).attr('realname'));
			$('#grantemail').html($(this).attr('email'));
			$('#grantuserid').val($(this).attr('userid'));
			$('#grantmwusername').val($(this).attr('mwusername'));
			$('#grantwikiid').val($(this).attr('wikiid'));
			$('#grantflag').val($(this).attr('checked') ? 1 : 0);
			if (!$(this).attr('checked')) {
				if (confirm("Do you really want to remove "+($(this).attr('realname') ? $(this).attr('realname') : "this user")+"'s write access to the \""+$(this).attr('wikiname')+"\" wiki?"))
					dialog_submit(this, "#granteditform");
			} else
				$('#granteditdialog').dialog('open');
			return false;
		});
	});
</script>
<div id="granteditdialog" class="wf-dialog">
	<form id="granteditform">
	<table>
		<tr><td class="formlabelleft nowrap">Wiki:</td><td><span id="grantwikiname" /> (<span id="grantwikititle" />)</td></tr>
		<tr><td class="formlabelleft nowrap">User to invite:</td><td><span id="grantrealname" /> (<span id="grantemail" />)</td></tr>
		<tr><td class="formlabelleft nowrap">Username on your wiki:</td><td><input type="text" id="grantmwusername" name="mwusername" value=" " /></td></tr>
	</table>
	<input type="hidden" name="wikiid" id="grantwikiid" value=" " />
	<input type="hidden" name="userid" id="grantuserid" value=" " />
	<input type="hidden" name="grantflag" id="grantflag" value="1" />
	<input type="hidden" name="ga_action" value="managewiki_editor" />
	</form>
	<div class="ui-widget" id="grantmessage">
		<div class="ui-state-highlight ui-corner-all wf-message-box ui-helper-hidden"></div>
	</div>
</div>
EOT;
	}

	// Claim an old account, served in a dialog box.
	function frag_claim_account() {	
		return <<<EOT
<div id="claimaccountdialog" class="wf-dialog">
	<p>Enter the username and password that you were using before the conversion to <strong>OpenID</strong> authentication.
	Please note that all existing user rights from your pre-OpenID account will be added to the OpenID-enabled account that you are currently using.</p>
	<form id="claimaccount">
	<table>
	<tr><td align=right>Username:</td><td><input type="text" id="claimusername" name="username" /></td></tr>
	<tr><td align=right>Password:</td><td><input type="password" id="claimpassword" name="password" /></td></tr>
	</table>
	<input type="hidden" name="ga_action" value="claimaccount" />
	<input type="hidden" name="ga_message_id" value="claimmessage" />
	</form>
	<div class="ui-widget" id="claimmessage">
		<div class="ui-state-highlight ui-corner-all wf-message-box ui-helper-hidden"></div>
	</div>
</div>
<script type="text/javascript">
$('#claimaccountdialog')
	.attr('title','Claim a Pre-OpenID Account')
	.dialog({ modal: true, autoOpen: false, width: 400, buttons: { 
		"Claim Account": function() { dialog_submit(this, "#claimaccount"); }
	} });
$('.claimaccountbutton').click(function(){
	$('#claimaccount input[type!=hidden]').val('');
	$('#claimmessage').hide();
	$('#claimaccountdialog').dialog('open');
	return false;
});
</script>
EOT;
	}

	// AJAX handlers

	function dispatch_ajax ($post) {
		if (!isset($post["ga_action"]))
			return $this->fail ("Client side error: no action requested.");
		if (!method_exists ($this, "ajax_" . $post["ga_action"]))
			return $this->fail ("Invalid request (action=".$post["ga_action"].")");
		try {
			return call_user_func (array ($this, "ajax_" . $post["ga_action"]), $post);
		} catch (Exception $e) {
			return $this->fail ($e->getMessage());
		}
	}

	function ajax_test_success ($post) {
		if (preg_match ('{^\d+$}', $post["sample_id"]))
			sleep ($post["sample_id"]);
		return array ("success" => true,
			      "message" => "Great success, \"$post[sample_id]\"!");
	}
	function ajax_test_failure ($post) {
		return array ("success" => false,
			      "message" => "That totally failed, \"$post[sample_id]\".");
	}
	function ajax_test_ajax_error ($post) {
		print "Unparseable.";
		exit;
	}
	function ajax_test_alert ($post) {
		return array ("success" => true,
			      "alert" => "I would like to alert you.",
			      "message" => "I alerted you.");
	}
	function ajax_test_alert_redirect ($post) {
		return array ("success" => true,
			      "alert" => "I would like to alert you and then redirect.",
			      "message" => "I alerted you.",
			      "redirect" => "/?tabActive=wikis");
	}
	function ajax_test_selecttab ($post) {
		return array ("success" => true,
			      "selecttab" => "groups");
	}
	function ajax_test_activated ($post) {
		if ($this->isActivated()) {
			return array ("success" => true,
				      "message" => "Yeah, your account is activated.");
		} else {
			return array ("success" => false,
				      "message" => "Sorry, your account is not yet activated.");
		}
	}
	function ajax_managewiki_groups ($post) {
		$wikiid = $post["wikiid"];
		$wiki = $this->getWiki($wikiid);
		if (!is_array ($wiki))
			return $this->fail ("No wiki specified.");
		if (!$this->isAdmin() && $wiki["userid"] != $this->openid)
			return $this->fail ("You are not allowed to do that.");

		$checkus = array();
		$uncheckus = array();
		$enableus = array();
		$disableus = array();

		// Note which users can view the wiki before we make changes
		$read_via_group_before = array();
		foreach ($this->getInvitedUsers ($wikiid) as $u)
			if ($u["read_via_group"])
				$read_via_group_before[$u["userid"]] = true;

		$want = $post["mw${wikiid}_groups"];
		foreach ($this->getAllGroups() as $g) {
			if ($g["groupid"] == "ADMIN") continue;
			if (!$want || false === array_search ($g["groupid"], $want)) {
				$this->disinviteGroup ($wikiid, $g["groupid"]);
				$uncheckus[] = "mw${wikiid}_group_".$g["groupid"];
			}
			else {
				$this->inviteGroup ($wikiid, $g["groupid"]);
				$checkus[] = "mw${wikiid}_group_".$g["groupid"];
			}
		}

		// Check which users can view the wiki now, and
		// check/uncheck "view" checkboxes in the "invite
		// users" table to show the change

		$read_via_group_after = array();
		$read_anyway_after = array();
		foreach ($this->getInvitedUsers ($wikiid) as $u)
			if ($u["read_via_group"])
				$read_via_group_after[$u["userid"]] = true;
			else
				$read_anyway_after[$u["userid"]] = true;

		foreach ($read_via_group_before as $userid => $x)
			if (!isset($read_via_group_after[$userid])) {
				if (!isset($read_anyway_after[$userid])) {
					$uncheckus[] = "mw${wikiid}_userview_".md5($userid);
					$uncheckus[] = "mw${wikiid}_useredit_".md5($userid);
				}
				$enableus[] = "mw${wikiid}_userview_".md5($userid);
			}

		foreach ($read_via_group_after as $userid => $x)
			if (!isset($read_via_group_before[$userid])) {
				$disableus[] = "mw${wikiid}_userview_".md5($userid);
				$checkus[] = "mw${wikiid}_userview_".md5($userid);
			}

		return array ("success" => true,
			      "check" => $checkus,
			      "uncheck" => $uncheckus,
			      "enable" => $enableus,
			      "disable" => $disableus);
	}

	function ajax_managewiki_users ($post) {
		$wikiid = $post["wikiid"];
		$wiki = $this->getWiki($wikiid);
		if (!$this->isAdmin() && $wiki["userid"] != $this->openid)
			return $this->fail ("You are not allowed to do that.");

		$checkus = array();
		$uncheckus = array();

		// Don't bother [dis]inviting (and telling the webgui
		// to [un]check) users who can view the wiki anyway by
		// virtue of being in a group.
		$read_via_group = array();
		$writeable = array();
		foreach ($this->getInvitedUsers ($wikiid) as $u) {
			if ($u["read_via_group"])
				$read_via_group[$u["userid"]] = true;
			if ($u["mwusername"])
				$writeable[$u["userid"]] = true;
		}

		foreach ($this->getAllActivatedUsers() as $u) {
			if (isset ($read_via_group[$u["userid"]]))
				continue;
			$userview_param = "mw${wikiid}_userview_".md5($u["userid"]);
			if (!(isset($post[$userview_param]) && $post[$userview_param])) {
				$this->disinviteUser ($wikiid, $u["userid"]);
				$uncheckus[] = $userview_param;
				$uncheckus[] = "mw${wikiid}_useredit_".md5($u["userid"]);
			}
			else {
				$this->inviteUser ($wikiid, $u["userid"]);
				$checkus[] = $userview_param;
			}
		}

		// Turn on the "edit" checkbox if a user has just
		// regained "view" privileges and still has autologin
		// (presumably obtained when having read access in the
		// past)
		foreach ($this->getInvitedUsers ($wikiid) as $u)
			if ($u["mwusername"])
				if (!isset ($writeable[$u["userid"]])) {
					$writeable[$u["userid"]] = true;
					$checkus[] = "mw${wikiid}_useredit_".md5($u["userid"]);
				}

		return array ("success" => true,
			      "check" => $checkus,
			      "uncheck" => $uncheckus);
	}

	function ajax_managewiki_editor ($post) {
		$checkus = array();
		if ($post["grantflag"]) {
			$this->validate_mwusername ($post["mwusername"]);
			$this->inviteUser ($post["wikiid"], $post["userid"], $post["mwusername"]);
			$check = "check";
			$checkus[] = "mw".$post["wikiid"]."_userview_".md5($post["userid"]);
		}
		else {
			$this->disinviteEditor ($post["wikiid"], $post["userid"]);
			$check = "uncheck";
		}
		$checkus[] = "mw".$post["wikiid"]."_useredit_".md5($post["userid"]);
		return $this->success(array ($check => $checkus));
	}

	function ajax_createwiki ($post) {
		$this->validate_activated();
		if (!$this->canCreateWikis())
			return $this->fail ("You have reached your wiki quota.  Please contact an administrator to increase your quota.");
		$post["realname"] = trim($post["realname"]);
		if ($post["realname"] == "")
			return $this->fail ("You must provide a title for your wiki.");
		if (!preg_match ('{^[-\w\' ]+$}', $post["realname"]))
			return $this->fail ("Your wiki title cannot contain quotation marks, symbols, or special characters.");

		$this->validate_wikiname ($post["wikiname"]);
		$this->validate_mwusername ($post["mwusername"]);

		if (!$this->isWikiNameAvailable ($post["wikiname"]))
			return $this->fail ("The wiki name \"$post[wikiname]\" is already in use.");

		$ok = $this->createWiki ($post["wikiname"],
					 $post["realname"],
					 $post["mwusername"],
					 isset($post["groups"]) ? $post["groups"] : array());
		if (!$ok)
			return $this->fail ("Something went wrong while setting up your wiki.  Please contact a site administrator before trying again.");
		return array ("success" => true,
			      "alert" => "Your wiki has been created.  You will be logged in to your new wiki now.",
			      "redirect" => "/".$post["wikiname"]."/Main_Page");
		
	}

	function ajax_setgroups ($post) {
		if (!$this->isAdmin()) return $this->fail ("You are not allowed to do that.");
		$this->Focus ($post["userid"]);
		$this->setGroups ($post["group_request"]);
		return $this->success(array("message" => "Changes saved."));
	}

	function ajax_requestgroups ($post) {		
		$this->requestGroup ($post["group_request"]);
		return array ("success" => true, "refreshtab" => true);
	}

	function ajax_requestwiki ($post) {
		$this->validate_activated();
		$wikiid = $post["wikiid"]+0;
		$hideus = array("button-request-$wikiid");
		if (isset ($post["mwusername"]) ||
		    (isset ($post["writeaccess"]) && $post["writeaccess"])) {
			$this->validate_mwusername ($post["mwusername"]);
			$this->requestWiki ($post["wikiid"]+0, $post["mwusername"]);
			$hideus[] = "button-requestwrite-$wikiid";
		} else
			$this->requestWiki ($post["wikiid"]+0);
		return $this->success (array ("hide" => $hideus,
					      "show" => array ("button-requestpending-$wikiid")));
	}

	function ajax_myaccount_save ($post) {
		$requestor_is_admin = $this->isAdmin();
		if (isset ($post["userid"]) && $post["userid"] != $this->openid) {
			if (!$this->_security( array( 'access'=>'admin', 'message'=>'Attempt to modify user ('.$post['userid'].') by non admin "'.$this->openid.'".' ))) 
				return array ("success" => false, "message" => "Access denied.");
			$this->Focus($post["userid"]);
		}
		$this->validate_email ($post["email"]);
		if (isset ($post["mwusername"]) && $post["mwusername"] != "")
			$this->validate_mwusername ($post["mwusername"]);
		$did_not_have_basics = !$this->getUserEmail() || !$this->getUserRealname();
		if ($requestor_is_admin && isset ($post["quota"])) {
			if (!is_numeric ($post["quota"]) || $post["quota"] < 0 )
				return $this->fail ("Wiki quota must be a valid number.");
			$this->setWikiQuota ($post["quota"] + 0);
		}
		$this->setUserEmail ($post["email"]);
		$this->setMWUsername ($post["mwusername"]);
		$this->setUserRealname ($post["realname"]);

		$prefs = $this->getUserPrefs ();
		foreach ($prefs as &$p)
			if (isset($post["pref_".$p["prefid"]]))
				$p["value"] = $post["pref_".$p["prefid"]];
			else
				$p["value"] = null;
		$this->setUserPrefs ($prefs);

		if ($did_not_have_basics && $this->getUserEmail() && $this->getUserRealname())
			return array ("success" => true,
				      "redirect" => "/");
		else
			return array ("success" => true,
				      "message" => "Changes saved.");
	}

	function ajax_approve_request ($post) {
		$this->approveRequestId ($post["requestid"]+0);
		return $this->success();
	}

	function ajax_reject_request ($post) {
		$this->rejectRequestId ($post["requestid"]+0);
		return $this->success();
	}

	function ajax_loginas ($post) {
		if (!preg_match ('{^loginselect-(\d+)$}', $post["ga_button_id"], $matches))
			return $this->fail ("Invalid request: no ga_button_id");
		$uri = "/";
		if ($post[$matches[0]] == "0")
			$uri = "/Special:Userlogin";
		else if (!$this->setAutologin ($matches[1], $post[$matches[0]]))
			return $this->fail ("Invalid request: no matching autologin");
		$w = $this->getWiki ($matches[1]);
		return $this->success (array ("redirect" => "/".$w["wikiname"].$uri));
	}

	function ajax_claimaccount ($post) {
		error_log(print_r($post,true));
		$wasActivated = $this->isActivated();
		$claimed = $this->claimInvitationByPassword ($post["username"], $post["password"]);
		$message = sprintf ("Authentication succeeded.  Claimed %d wiki%s, %d group%s, and %d individual wiki invitation%s.",
				    $claimed["wikis"], $claimed["wikis"]==1?"":"s",
				    $claimed["groups"], $claimed["groups"]==1?"":"s",
				    $claimed["access"], $claimed["access"]==1?"":"s");
		$response = array ("message" => $message);
		if (!$wasActivated && $claimed["groups"]) {
			$response["redirect"] = "/";
			$this->selfActivate();
		} else if ($claimed["groups"])
			$response["refreshtab"] = true;
		return $this->success ($response);
	}

	function validate_wikiname ($x) {
		if (!preg_match ('{^[a-z][a-z0-9]{2,12}$}', $x))
			throw new Exception ("Your wiki name must be 3 to 12 lower case letters and digits, and must start with a letter.");
	}

	function validate_mwusername ($x) {
		if (!preg_match ('{^[a-z][-a-z0-9_\.]*$}i', $x))
			throw new Exception ("A MediaWiki username must contain only letters, digits, underscores, dots, and dashes, and must begin with a letter.");
	}

	function validate_email ($x) {
		if (!preg_match ('{^[-_\.a-z0-9]+@[-_\.a-z0-9]+\.[a-z]+$}i', $x))
			throw new Exception ("That email address does not look like an email address.");
	}

	function validate_activated () {
		if (!$this->isActivated())
			throw new Exception ("You are not allowed to do that.");
	}

	function fail($message="Server side error.") {
		return array ("success" => false,
			      "message" => $message,
			      "alert" => $message);
	}
	function success($message="OK") {
		if (is_array ($message))
			return array_merge (array ("success" => true), $message);
		return array ("success" => true,
			      "message" => $message);
	}

}  // class ends




// misc functions

// usage: "Request pending since " . PMRelativeTime($grouplist['pending_since'][$i]);
// output: "5 days ago", etc
function PMRelativeTime($date) {
	if ($date+0 == 0) $date = strtotime($date);
	$diff = time() - $date;
	if ($diff<60) {
		$r = "$diff second";
	} else {
		$diff = round($diff/60);
		if ($diff<60) {
			$r = "$diff minute";
		} else {
			$diff = round($diff/60);
			if ($diff<24) {
				$r = "$diff hour";
			} else {
				$diff = round($diff/24);
				if ($diff<7) {
					$r = "$diff day";
				} else {
					$diff = round($diff/7);
					if ($diff<4) {
						$r = "$diff week";
					} else {
						return date("F j, Y", $date);
					}
				}
			}
		}
	}
	return $r . ($diff !=1 ? 's' : '') . " ago";
}

?>
