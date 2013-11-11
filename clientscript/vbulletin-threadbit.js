/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/
vB_XHTML_Ready.subscribe(init_ThreadBits);function init_ThreadBits(){var B=YAHOO.util.Dom.getElementsByClassName("threadbit","li","threadlist");for(var A=0;A<B.length;A++){new ThreadBit(B[A])}}function ThreadBit(A){this.threadbit=YAHOO.util.Dom.get(A);this.handle_description()}ThreadBit.prototype.handle_description=function(){var A=YAHOO.util.Dom.getElementsByClassName("threaddesc","p",this.threadbit);if(A&&A[0]&&A[0].firstChild){this.description=A[0].firstChild.nodeValue;YAHOO.util.Dom.getElementsByClassName("threadinfo","div",this.threadbit)[0].title=this.description}};