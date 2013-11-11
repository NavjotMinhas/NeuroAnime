/*!======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

function vB_Upgrade()
{
	YAHOO.util.Event.on("customerid", "focus", this.customerfocus, this, true);
	YAHOO.util.Event.on("showdetails", "click", this.toggledetails, this, true);
	YAHOO.util.Event.on("hidedetails", "click", this.toggledetails, this, true);
	YAHOO.util.Event.on("querystatus", "click", this.querystatus, this, true);
	YAHOO.util.Event.on("detailboxclose", "click", this.hidedetails, this, true);
	YAHOO.util.Event.on("promptform", "submit", this.promptsubmit, this, true);
	YAHOO.util.Event.on("promptcancel", "click", this.promptcancel, this, true);
	YAHOO.util.Event.on("promptresponse", "keypress", this.promptentry, this, true);
	YAHOO.util.Event.on("confirmform", "submit", this.confirmsubmit, this, true);
	YAHOO.util.Event.on("confirmok", "click", this.confirmsubmit, this, true);
	YAHOO.util.Event.on("confirmcancel", "click", this.confirmsubmit, this, true);
	YAHOO.util.Event.on("admincp", "click", function() { window.location = ADMINDIR; });
	YAHOO.util.Event.on("beginupgrade", "click", this.beginupgrade, this, true);
	// start the processing..

	this.upgradelog = YAHOO.util.Dom.get("mainmessage");
	var ul = document.createElement("ul");
	this.upgradeloglist = this.upgradelog.appendChild(ul);
	YAHOO.util.Dom.addClass(this.upgradeloglist, "list_no_decoration");

	this.lastmessagenode = null;
	this.replacelastmessage = 0;
	this.logcount = 0;
	this.stepsdone = 0;
	this.prevstep = '';
	this.promptinfo = {};
	this.background = YAHOO.util.Dom.get("vb_overlay_background");
	this.promptbox = YAHOO.util.Dom.get("prompt");
	this.confirmbox = YAHOO.util.Dom.get("confirm");
	this.events_enabled = false;
	this.confirm_callback = null;
	this.ajax_query_req = null;
	this.timer = null;
	this.confirm_button_text = YAHOO.util.Dom.get("confirmok").value;
	this.cancel_button_text = YAHOO.util.Dom.get("confirmcancel").value;
	this.skipok = false;
	this.target = SETUPTYPE == 'install' ? 'install.php' : 'upgrade.php';
	this.confirmtitle = YAHOO.util.Dom.get("confirmtitle").innerHTML;
	this.prompttitle = YAHOO.util.Dom.get("prompttitle").innerHTML;
	this.percentout = true;
}

vB_Upgrade.prototype.beginupgrade = function(e)
{
	YAHOO.util.Event.stopEvent(e);
	YAHOO.util.Dom.addClass("beginsection", "hidden");
	YAHOO.util.Dom.removeClass("progresssection", "hidden");
	this.process_step(SCRIPTINFO['version'], SCRIPTINFO['step'], SCRIPTINFO['startat'], true, false, true, SCRIPTINFO['only']);
}

vB_Upgrade.prototype.querystatus = function(step)
{
	YAHOO.util.Dom.get("querystatus").disabled = true;
	var postdata = "ajax=1&status=1&" + PHP.urlencode(CUSTNUMBER);

	var callback =
	{
		failure : vBulletin_AJAX_Error_Handler,
		timeout : 0,
		success : this.process_querystatus,
		scope   : this,
		argument: {"step" : step}
	}
	if (YAHOO.util.Connect.isCallInProgress(this.ajax_query_req))
	{
		YAHOO.util.Connect.abort(this.ajax_query_req);
	}
	this.ajax_query_req = YAHOO.util.Connect.asyncRequest("POST", this.target, callback, postdata);
}

vB_Upgrade.prototype.process_querystatus = function(ajax)
{
	if (ajax.responseXML)
	{
		YAHOO.util.Dom.get("querystatus").disabled = false;
		var processes = ajax.responseXML.getElementsByTagName("process");
		if (processes.length)
		{
			if (YAHOO.util.Dom.hasClass("detailbox", "hidden"))
			{
				this.toggledetails();
			}
			var output = '';
			for (var i = 0; i < processes.length; i++)
			{
				this.log_message(ajax.responseXML.getElementsByTagName("query_status")[0].firstChild.nodeValue, "querystatus_header");
				this.log_message(processes[i].firstChild.nodeValue, "querystatus_message");
			}
		}
		else
		{
			var noprocesses = ajax.responseXML.getElementsByTagName("noprocess");
			if (noprocesses.length)
			{
				alert(noprocesses[0].firstChild.nodeValue);
			}
		}
	}
}

vB_Upgrade.prototype.process_step = function(version, step, startat, checktable, response, firstrun, only, htmldata)
{
	this.showprogress();
	var postdata =
		"ajax=1&version=" + PHP.urlencode(version) +
		"&checktable=" + checktable +
		"&firstrun=" + (firstrun ? true : false) +
		"&step=" + step +
		"&startat=" + startat +
		"&only=" + (only == 1 ? true : false) +
		"&customerid=" + PHP.urlencode(CUSTNUMBER);

	if (typeof(response) != "undefined" && typeof(response) != "boolean" && response != null)
	{
		postdata += "&response=" + PHP.urlencode(response);
	}
	if (htmldata)
	{
		postdata += '&' + htmldata;
	}

	var callback =
	{
		failure : this.ajax_failure,
		timeout : 0,
		success : this.process_step_result,
		scope   : this,
		argument: {
			"step"    : step,
			"version" : version
		}
	}
	YAHOO.util.Connect.asyncRequest("POST", this.target, callback, postdata);
	// Start process timer.
	var thisC = this;
	this.timer = setTimeout(function(){ thisC.show_query_status(version); }, 20000);
}

vB_Upgrade.prototype.cancel_query_status = function()
{
	YAHOO.util.Dom.addClass("querystatus", "hidden");
	YAHOO.util.Dom.get("querystatus").disabled = false;
	clearTimeout(this.timer);
	if (YAHOO.util.Connect.isCallInProgress(this.ajax_query_req))
	{
		YAHOO.util.Connect.abort(this.ajax_query_req);
	}
}

vB_Upgrade.prototype.ajax_failure = function(ajax)
{
	vBulletin_AJAX_Error_Handler(ajax);
	this.cancel_query_status();
}

vB_Upgrade.prototype.show_query_status = function(version)
{
	if (version.match(/^\d/))
	{
		YAHOO.util.Dom.removeClass("querystatus", "hidden");
	}
}

vB_Upgrade.prototype.process_step_result = function(ajax)
{
	var checktable = true;
	var startat = 0;

	if (ajax.responseXML)
	{
		this.cancel_query_status();
		var errors = ajax.responseXML.getElementsByTagName("error");
		var errors_html = ajax.responseXML.getElementsByTagName("error_html");
		var errorlist = null;
		if (errors.length)
		{
			errorlist = errors;
		}
		if (errors_html.length)
		{
			errorlist = errors_html;
		}
		if (errorlist)
		{
			for (var i = 0; i < errorlist.length; i++)
			{
				this.log_message(errorlist[i].firstChild.nodeValue);
				this.show_confirm(errorlist[i].firstChild.nodeValue, null, true);
			}
			this.abort_upgrade();
			return;
		}

		// Do we have a status?
		var status = ajax.responseXML.getElementsByTagName("status");
		if (status.length)
		{
			YAHOO.util.Dom.get("statusmessage").innerHTML = ajax.responseXML.getElementsByTagName("status")[0].firstChild.nodeValue;
		}
		else
		{	// If we have no status then assume we received unexpected text!
			this.process_bad_response(ajax.responseText, ajax.argument.step, ajax.argument.version);
			return;
		}

		YAHOO.util.Dom.get("progressmessage").innerHTML = ajax.responseXML.getElementsByTagName("status")[0].firstChild.nodeValue;
		var longversion = ajax.responseXML.getElementsByTagName("longversion")[0].firstChild.nodeValue;
		var version = ajax.responseXML.getElementsByTagName("version")[0].firstChild.nodeValue;
		var nextstep = ajax.responseXML.getElementsByTagName("nextstep")[0].firstChild.nodeValue;
		var startatnode = ajax.responseXML.getElementsByTagName("startat");
		if (startatnode.length)
		{
			startat = startatnode[0].firstChild.nodeValue;
		}

		this.promptinfo = {
			"version"    : version,
			"nextstep"   : nextstep,
			"startat"    : startat,
			"checktable" : checktable
		};

		var showstep = false;
		var stepinfo = version + "-" + nextstep;

		var prompt = ajax.responseXML.getElementsByTagName("prompt");
		var html = ajax.responseXML.getElementsByTagName("html");

		if (startat == 0 && !prompt.length && !html.length && (!this.prevstep || this.prevstep != stepinfo))
		{
			this.prevstep = stepinfo;
			this.stepsdone++;

			var percent = Math.floor((this.stepsdone / TOTALSTEPS) * 100);
			YAHOO.util.Dom.setStyle("progressbar", "width", percent + "%");

			if (percent > 0)
			{
				if (this.percentout)
				{
					YAHOO.util.Dom.get("percentageout").innerHTML = percent + "%";
					var percentoutwidth = YAHOO.util.Dom.get("percentageout").offsetWidth;
					var progresswidth = YAHOO.util.Dom.get("progressbar").offsetWidth;
					if (progresswidth >= percentoutwidth)
					{
						this.percentout = false;
						YAHOO.util.Dom.get("percentageout").innerHTML = "";
						YAHOO.util.Dom.get("progressbar").innerHTML = "<span>" + percent + "%</span>";
					}
				}
				else
				{
					YAHOO.util.Dom.get("progressbar").innerHTML = "<span>" + percent + "%</span>";
				}
			}

			showstep = true;
		}
		else if (startat == 1)
		{
			showstep = true;
		}

		var upgradenotice = ajax.responseXML.getElementsByTagName("upgradenotice");
		if (upgradenotice.length && !prompt.length && !html.length)
		{
			this.log_message(upgradenotice[0].firstChild.nodeValue);
		}

		var messages = ajax.responseXML.getElementsByTagName("message");
		if (messages.length)
		{
			for (var i = 0; i < messages.length; i++)
			{
				var message = messages[i].firstChild.nodeValue;
				if (showstep && i == 0)
				{
					message = construct_phrase(STEP_X_Y, ajax.argument.step, message);
				}
				this.log_message(message, null, YAHOO.util.Dom.getAttribute(messages[i], "replace"));
			}
		}

		var apperror = ajax.responseXML.getElementsByTagName("apperror");
		if (apperror.length)
		{
			switch (YAHOO.util.Dom.getAttribute(apperror[0], "type"))
			{
				case "APP_CREATE_TABLE_EXISTS":
					this.show_confirm(apperror[0].firstChild.nodeValue, this.confirmtable);
					return;
					break;
				case "PHP_TRIGGER_ERROR":
					this.log_message(apperror[0].firstChild.nodeValue, "noindent");
					this.show_confirm(apperror[0].firstChild.nodeValue, null, true);
					this.abort_upgrade();
					return;
					break;
				case "MYSQL_HALT":
					this.log_message(apperror[0].firstChild.nodeValue, "noindent");
					this.show_confirm(apperror[0].firstChild.nodeValue, null, true);
					this.abort_upgrade();
					return;
					break;
			}
		}

		var reset = ajax.responseXML.getElementsByTagName("reset").length ?  true : false;
		var title = ajax.responseXML.getElementsByTagName("title").length ?  ajax.responseXML.getElementsByTagName("title")[0].firstChild.nodeValue : null;
		var hidecancel = ajax.responseXML.getElementsByTagName("hidecancel").length ?  true : false;
		var cancel = ajax.responseXML.getElementsByTagName("cancel");
		var ok = ajax.responseXML.getElementsByTagName("ok");
		var oktext = ok.length ? ok[0].firstChild.nodeValue : null;
		var canceltext = cancel.length ? cancel[0].firstChild.nodeValue : null;
		var width = ajax.responseXML.getElementsByTagName("width").length ?  ajax.responseXML.getElementsByTagName("width")[0].firstChild.nodeValue : null;
		var height = ajax.responseXML.getElementsByTagName("height").length ?  ajax.responseXML.getElementsByTagName("height")[0].firstChild.nodeValue : null;

		if (prompt.length)
		{
			var confirm = ajax.responseXML.getElementsByTagName("confirm");
			if (confirm.length)
			{
				this.show_confirm(prompt[0].firstChild.nodeValue, this.confirm, hidecancel, oktext, canceltext, width, height, title, false, reset);
			}
			else
			{
				this.show_prompt(prompt[0].firstChild.nodeValue, canceltext, title, reset);
			}
			return;
		}

		if (html.length)
		{
			this.show_confirm(html[0].firstChild.nodeValue, this.confirmhtml, hidecancel, oktext, canceltext, width, height, title, true, reset);
			return;
		}

		if (version == "done")
		{
			// do something here....
			var oktext = ajax.responseXML.getElementsByTagName("oktext")[0].firstChild.nodeValue;
			var canceltext = ajax.responseXML.getElementsByTagName("canceltext")[0].firstChild.nodeValue;
			this.show_confirm(ajax.responseXML.getElementsByTagName("upgradecomplete")[0].firstChild.nodeValue, this.exitupgrade, false, oktext, canceltext);
			return;
		}
		else
		{
			this.process_step(version, nextstep, startat, checktable, null, null, SCRIPTINFO['only']);
		}
	}
	else if (ajax.responseText)
	{
		this.process_bad_response(ajax.responseText, ajax.argument.step, ajax.argument.version);
		return;
	}
}

vB_Upgrade.prototype.process_bad_response = function(text, step, version)
{
	var newtext = text.replace(/^<\?xml version="[^"]+" encoding="[^"]+"\?>\n/, '');
	if (newtext.length == 0)
	{
		this.show_confirm(SERVER_NO_RESPONSE, null, true);
		this.abort_upgrade();
		return;
	}
	
	if (text.match(/fatal error.*maximum execution time/i))
	{
		//  Only the template merge allows skip at present
		if (version == "final" && step == 6)
		{
			// Tell step 6 that it timed out so that it will output the proper "time out" phrase and continue
			// on with the next step. That could be done right here without calling the script again but this
			// way allows us to continue with the flow of the step process without sticking in branches
			this.process_step(version, step, 0, false, "timeout", null, SCRIPTINFO['only']);
			return;
		}
	}
	this.show_confirm(construct_phrase(UNEXPECTED_TEXT, PHP.htmlspecialchars(text)), null, true);
	this.abort_upgrade();	
}

vB_Upgrade.prototype.confirmhtml = function(e, confirm)
{
	var response = confirm ? 'yes' : 'no';

	if (response == 'yes')
	{
		var confirmform = YAHOO.util.Dom.get("confirmform");
		var inputs = confirmform.getElementsByTagName("input");
		var continueok = true;
		for (i = 0; i < inputs.length; i++)
		{
			var require = YAHOO.util.Dom.getAttribute(inputs[i], "vbrequire");
			if (require == 1)
			{
				YAHOO.util.Dom.addClass(inputs[i].name + "_error", "hidden");
			}

			var value = PHP.trim(inputs[i].value);
			if (require == 1 && value == "")
			{
				YAHOO.util.Dom.removeClass(inputs[i].name + "_error", "hidden");
				continueok = false;
			}
		}

		if (!continueok)
		{
			return false;
		}
	}

	var psuedoform = new vB_Hidden_Form();
	psuedoform.add_variables_from_object(this.confirmbox);
	var htmldata = 'htmlsubmit=1&' + psuedoform.build_query_string();

	this.process_step(this.promptinfo.version, this.promptinfo.nextstep, this.promptinfo.startat, false, response, null, SCRIPTINFO['only'], htmldata);

	return true;
}

vB_Upgrade.prototype.confirm = function(e, confirm)
{
	var response = confirm ? 'yes' : 'no';
	this.process_step(this.promptinfo.version, this.promptinfo.nextstep, this.promptinfo.startat, false, response, null, SCRIPTINFO['only']);
	return true;
}

vB_Upgrade.prototype.confirmtable = function(e, confirm)
{
	if (confirm)
	{
		this.process_step(this.promptinfo.version, this.promptinfo.nextstep, this.promptinfo.startat, false, null, null, SCRIPTINFO['only']);
	}
	else
	{
		this.abort_upgrade();
	}

	return true;
}

vB_Upgrade.prototype.abort_upgrade = function()
{
	YAHOO.util.Dom.get("statusmessage").innerHTML = ABORTMSG;
	YAHOO.util.Dom.get("progressmessage").innerHTML = ABORTMSG;
}

vB_Upgrade.prototype.log_message = function(text, classname, replace)
{
	var li = document.createElement("li");
	if (classname)
	{
		YAHOO.util.Dom.addClass(li, classname);
	}
	li.innerHTML = text;
	if (replace == 1 && this.replacelastmessage == 1)
	{
		this.upgradeloglist.replaceChild(li, this.lastmessagenode);
	}
	else
	{
		this.upgradeloglist.appendChild(li);
	}
	this.lastmessagenode = li;
	this.replacelastmessage = replace;
	/*
	this.logcount++;
	if (this.logcount > 20)
	{
		this.upgradeloglist.removeChild(this.upgradeloglist.firstChild);
	}
	*/
	this.upgradelog.scrollTop = this.upgradelog.scrollHeight;
}

vB_Upgrade.prototype.process_prompt = function(response)
{
	response = (response == false || response == null) ? "" : response;
	this.process_step(version, nextstep, startat, checktable, response, null, null, SCRIPTINFO['only']);
}

/**
* Show the details div
*
*/
vB_Upgrade.prototype.toggledetails = function()
{
	if (YAHOO.util.Dom.hasClass("detailbox", "hidden"))
	{
		YAHOO.util.Dom.removeClass("detailbox", "hidden");
		YAHOO.util.Dom.addClass("showdetails", "hidden");
		YAHOO.util.Dom.removeClass("hidedetails", "hidden");
	}
	else
	{
		YAHOO.util.Dom.addClass("detailbox", "hidden");
		YAHOO.util.Dom.addClass("hidedetails", "hidden");
		YAHOO.util.Dom.removeClass("showdetails", "hidden");
	}
}

/**
* Hide the details div
*
*/
vB_Upgrade.prototype.hidedetails = function()
{
	YAHOO.util.Dom.addClass("detailbox", "hidden");
}

/**
* Show the ajax progress indicator
*
*/
vB_Upgrade.prototype.showprogress = function()
{
	YAHOO.util.Dom.removeClass("upgradeprogress", "hidden");
}

/**
* Hide the ajax progress indicator
*
*/
vB_Upgrade.prototype.hideprogress = function()
{
	YAHOO.util.Dom.addClass("upgradeprogress", "hidden");
}

/**
* Set focus on customer input
*
*/
vB_Upgrade.prototype.customerfocus = function(e)
{
	YAHOO.util.Dom.addClass("customerid_error", "hidden");
}

vB_Upgrade.prototype.show_confirm = function(text, confirm_callback, hidecancel, oktext, canceltext, width, height, title, advanced, reset)
{
	if (reset)
	{
		YAHOO.util.Dom.removeClass("confirmreset", "hidden");
	}
	else
	{
		YAHOO.util.Dom.addClass("confirmreset", "hidden");
	}
	if (title)
	{
		YAHOO.util.Dom.get("confirmtitle").innerHTML = title;
	}
	else
	{
		YAHOO.util.Dom.get("confirmtitle").innerHTML = this.confirmtitle;
	}

	if (advanced)
	{
		YAHOO.util.Dom.addClass(this.confirmbox, "advancedconfirm");
	}
	else
	{
		YAHOO.util.Dom.removeClass(this.confirmbox, "advancedconfirm");
	}

	if (oktext)
	{
		YAHOO.util.Dom.get("confirmok").value = oktext;
	}
	else
	{
		YAHOO.util.Dom.get("confirmok").value = this.confirm_button_text;
	}

	if (canceltext)
	{
		YAHOO.util.Dom.get("confirmcancel").value = canceltext;
	}
	else
	{
		YAHOO.util.Dom.get("confirmcancel").value = this.cancel_button_text;
	}
	if (width)
	{
		YAHOO.util.Dom.setStyle(this.confirmbox, "width", width);
	}
	else
	{
		YAHOO.util.Dom.setStyle(this.confirmbox, "width", "");
	}
	if (height)
	{
		YAHOO.util.Dom.setStyle(this.confirmbox, "height", height);
	}
	else
	{
		YAHOO.util.Dom.setStyle(this.confirmbox, "height", "");
	}

	this.hideprogress();
	this.confirm_callback = confirm_callback;

	YAHOO.util.Dom.setStyle("confirmmessage", "height", "auto");
	YAHOO.util.Dom.get("confirmmessage").innerHTML = text;

	if (hidecancel)
	{
		YAHOO.util.Dom.addClass("confirmcancel", "hidden");
	}
	else
	{
		YAHOO.util.Dom.removeClass("confirmcancel", "hidden");
	}
	this.move_box(this.confirmbox);

	var inputs = this.confirmbox.getElementsByTagName("input");
	for (i = 0; i < inputs.length; i++)
	{
		if (inputs[i].type == "text")
		{
			inputs[i].focus();
			break;
		}
	}

	if (is_ie6)
	{
		var region = YAHOO.util.Dom.getRegion("confirmmessage");
		if (region.height > 400)
		{
			YAHOO.util.Dom.setStyle("confirmmessage", "height", "400px");
		}
	}
}

vB_Upgrade.prototype.confirmsubmit = function(e)
{
	YAHOO.util.Event.stopEvent(e);
	var target = YAHOO.util.Event.getTarget(e);
	var closebox = true;
	if (this.confirm_callback)
	{
		var result = this.confirm_callback.call(this, e, target.id == "confirmok" ? true : false);
		if (!result)
		{
			closebox = false;
		}
	}
	if (closebox)
	{
		this.hide_box(this.confirmbox);
	}
}

vB_Upgrade.prototype.exitupgrade = function(e, confirm)
{
	if (confirm)
	{
		window.location = ADMINDIR;
	}
	else
	{
		YAHOO.util.Dom.removeClass("admincp", "hidden");
	}

	return true;
}

vB_Upgrade.prototype.show_prompt = function(text, cancel, title, reset)
{
	if (reset)
	{
		YAHOO.util.Dom.removeClass("promptreset", "hidden");
	}
	else
	{
		YAHOO.util.Dom.addClass("promptreset", "hidden");
	}
	if (title)
	{
		YAHOO.util.Dom.get("prompttitle").innerHTML = title;
	}
	else
	{
		YAHOO.util.Dom.get("prompttitle").innerHTML = this.prompttitle;
	}

	if (cancel)
	{
		YAHOO.util.Dom.get("promptcancel").value = cancel;
		YAHOO.util.Dom.removeClass("promptcancel", "hidden");
	}
	else
	{
		YAHOO.util.Dom.addClass("promptcancel", "hidden");
	}
	this.hideprogress();
	if (text)
	{
		YAHOO.util.Dom.get("promptmessage").innerHTML = text;
	}
	YAHOO.util.Dom.get("promptresponse").value = "";
	this.move_box(this.promptbox);
	YAHOO.util.Dom.get("promptresponse").focus();
}

vB_Upgrade.prototype.move_box = function(element)
{
	this.show_background();
	YAHOO.util.Dom.removeClass(element, "hidden");
	center_element(element, false, false, 100);
}

vB_Upgrade.prototype.show_background = function()
{
	viewport_info = null;
	var vpi = fetch_viewport_info();
	YAHOO.util.Dom.setStyle(this.background, "top", "0px");
	YAHOO.util.Dom.setStyle(this.background, "display", "");
	YAHOO.util.Dom.setStyle(this.background, "width", vpi["w"] + "px");
	YAHOO.util.Dom.setStyle(this.background, "height", vpi["h"] + "px");
	YAHOO.util.Dom.setXY(this.background, [vpi["x"], vpi["y"]]);
	YAHOO.util.Dom.removeClass(this.background, "hidden");

	if (!this.events_enabled)
	{
		YAHOO.util.Event.on(window, "resize", (is_ie ? this.handle_viewport_change_ie : this.handle_viewport_change), this, true);
		YAHOO.util.Event.on(window, "scroll", (is_ie ? this.handle_viewport_change_ie : this.handle_viewport_change), this, true);
		YAHOO.util.Event.on(window, "keydown", this.cancelform, this, true);
		this.events_enabled = true;
	}
}

vB_Upgrade.prototype.hide_background = function()
{
	YAHOO.util.Dom.addClass(this.background, "hidden");
	if (this.events_enabled)
	{
		YAHOO.util.Event.removeListener(window, "resize", (is_ie ? this.handle_viewport_change_ie : this.handle_viewport_change));
		YAHOO.util.Event.removeListener(window, "scroll", (is_ie ? this.handle_viewport_change_ie : this.handle_viewport_change));
		YAHOO.util.Event.removeListener(window, "keydown", this.cancelform);
		this.events_enabled = false;
	}
}

/**
* Resizes and repositions the overlaybox and the window overlay after the browser viewport has altered
*
*/
vB_Upgrade.prototype.handle_viewport_change = function()
{
	if (!YAHOO.util.Dom.hasClass(this.promptbox, "hidden"))
	{
		this.move_box(this.promptbox);
	}
	if (!YAHOO.util.Dom.hasClass(this.confirmbox, "hidden"))
	{
		this.move_box(this.confirmbox);
	}
}

/**
* Special case for IE - starts handle_viewport_change after a short delay
*
*/
vB_Upgrade.prototype.handle_viewport_change_ie = function()
{
	var thisC = this;
	setTimeout(function(){ thisC.handle_viewport_change(); }, 100);
}

vB_Upgrade.prototype.hide_box = function(element)
{
	this.hide_background();
	YAHOO.util.Dom.addClass(element, "hidden");
}

vB_Upgrade.prototype.promptcancel = function(e)
{
	this.skipok = true;
	YAHOO.util.Dom.get("promptresponse").value = '';
}

vB_Upgrade.prototype.promptsubmit = function(e)
{
	YAHOO.util.Event.stopEvent(e);
	var response = YAHOO.util.Dom.get("promptresponse").value;
	if (response == "" && !this.skipok)
	{
		YAHOO.util.Dom.setStyle("enter_response", "font-weight", "bold");
		YAHOO.util.Dom.get("promptresponse").focus();
		return;
	}
	this.hide_box(this.promptbox);
	this.process_step(this.promptinfo.version, this.promptinfo.nextstep, this.promptinfo.startat, this.promptinfo.checktable, response, null, SCRIPTINFO['only']);
}

vB_Upgrade.prototype.promptentry = function(e)
{
	if (e.keyCode == 10 || e.keyCode == 13)
	{
		this.promptsubmit(e);
	}
}

vB_Upgrade.prototype.cancelform = function(e)
{
	if (e.keyCode == 27)
	{
		YAHOO.util.Event.stopEvent(e);
		if (!YAHOO.util.Dom.hasClass(this.confirmbox, "hidden"))
		{
			this.hide_box(this.confirmbox);
			this.confirm_callback.call(this, e, false);
		}

		if (!YAHOO.util.Dom.hasClass(this.promptbox, "hidden"))
		{
			this.hide_box(this.promptbox);
			this.process_step(this.promptinfo.version, this.promptinfo.nextstep, this.promptinfo.startat, this.promptinfo.checktable, "", null, SCRIPTINFO['only']);
		}
	}
}

var upgradeobj = new vB_Upgrade();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 26385 $
|| ####################################################################
\*======================================================================*/