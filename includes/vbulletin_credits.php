<?php
/*======================================================================*\
|| #################################################################### ||
|| # vBulletin 4.1.5 Patch Level 1 - Licence Number VBF1F15E74
|| # ---------------------------------------------------------------- # ||
|| # Copyright ©2000-2011 vBulletin Solutions Inc. All Rights Reserved. ||
|| # This file may not be redistributed in whole or significant part. # ||
|| # ---------------- VBULLETIN IS NOT FREE SOFTWARE ---------------- # ||
|| # http://www.vbulletin.com | http://www.vbulletin.com/license.html # ||
|| #################################################################### ||
\*======================================================================*/

if (!isset($GLOBALS['vbulletin']->db))
{
	exit;
}

// display the credits table for use in admin/mod control panels

print_form_header('index', 'home');
print_table_header($vbphrase['vbulletin_developers_and_contributors']);
print_column_style_code(array('white-space: nowrap', ''));
print_label_row('<b>' . $vbphrase['software_developed_by'] . '</b>', '
	<a href="http://www.vbulletin.com/" target="vbulletin">vBulletin Solutions, Inc.</a>,
	<a href="http://www.internetbrands.com/" target="vbulletin">Internet Brands, Inc.</a>
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['business_product_development'] . '</b>', '
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=290027" target="vbulletin">Fabian Schonholz</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=297460" target="vbulletin">Alan Chiu</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=257345" target="vbulletin">Michael Anders</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=226054" target="vbulletin">Adrian Harris</a>
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['engineering'] . '</b>', '
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=214190" target="vbulletin">Kevin Sours</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=224" target="vbulletin">Freddie Bingham</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=250802" target="vbulletin">Edwin Brown</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=216095" target="vbulletin">David Grove</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=299765" target="vbulletin">Zoltan Szalay</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=315040" target="vbulletin">Jorge Tiznado</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=316570" target="vbulletin">Ivan Milanez</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=313947" target="vbulletin">Alan Orduno</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=284061" target="vbulletin">Michael Lavaveshkul</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=305211" target="vbulletin">Stefano Acerbetti</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=23871" target="vbulletin">Xiaoyu Huang</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=314912" target="vbulletin">Oscar Ulloa</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=317761" target="vbulletin">Kyle Furlong</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=323074" target="vbulletin">Fernando Varesi</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=326711" target="vbulletin">Glenn Vergara</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=57151" target="vbulletin">Paul Marsden</a>
', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['qa'] . '</b>', '
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=255358" target="vbulletin">Allen Lin</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=255359" target="vbulletin">Meghan Sensenbach</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=282066" target="vbulletin">Joanna W.H.</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=300526" target="vbulletin">Reshmi Rajesh</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=304681" target="vbulletin">Ruth Navaneetha</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=229357" target="vbulletin">Sebastiano Vassellatti</a>
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['support'] . '</b>', '
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=656" target="vbulletin">Steve Machol</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=868" target="vbulletin">Wayne Luke</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=245" target="vbulletin">George Liu</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=2026" target="vbulletin">Jake Bunce</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=19405" target="vbulletin">Zachery Woods</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=30801" target="vbulletin">Carrie Anderson</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=57702" target="vbulletin">Lynne Sands</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=19738" target="vbulletin">Trevor Hannant</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=168289" target="vbulletin">Marlena Machol</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=301796" target="vbulletin">Kay Alley</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=296606" target="vbulletin">Danny Morlette</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=303822" target="vbulletin">Zuzanna Grande</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=303322" target="vbulletin">Jasper Aguila</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=54891" target="vbulletin">Dody</a>
', '', 'top', NULL, false);

print_label_row('<b>' . $vbphrase['special_thanks_and_contributions'] . '</b>', '
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=46668" target="vbulletin">Ace Shattock</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=231844" target="vbulletin">Adrian Sacchi</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=281183" target="vbulletin">Ahmed</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=284675" target="vbulletin">Ajinkya Apte</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=42096" target="vbulletin">Andreas Kirbach</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=260819" target="vbulletin">Andrew Elkins</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=42534" target="vbulletin">Andy Huang</a>,
	Aston Jay,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=44710" target="vbulletin">Billy Golightly</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=28047" target="vbulletin">bjornstrom</a>,
	Bob Pankala,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=308514" target="vbulletin">Brad Wright</a>,
	Brian Swearingen,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=9215" target="vbulletin">Brian Gunter</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=5755" target="vbulletin">Chen Avinadav</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=263569" target="vbulletin">Chevy Revata</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=193838" target="vbulletin">Chris Holland</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=66209" target="vbulletin">Christopher Riley</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=48331" target="vbulletin">Colin Frei</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=25207" target="vbulletin">Daniel Clements</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=218637" target="vbulletin">Darren Gordon</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=215955" target="vbulletin">David Bonilla</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=95219" target="vbulletin">David Webb</a>,
	David Yancy,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=43093" target="vbulletin">digitalpoint</a>,
	Dominic Schlatter,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=259494" target="vbulletin">Don Kuramura</a>,
	Don T. Romrell,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=65" target="vbulletin">Doron Rosenberg</a>,
	Elmer Hernandez,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=297944" target="vbulletin">Eric Johney</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=126937" target="vbulletin">Eric Sizemore (SecondV)</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=255355-fleung" target="vbulletin">Fei Leung</a>,
	Fernando Munoz,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=11606" target="vbulletin">Floris Fiedeldij Dop</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=116999" target="vbulletin">Harry Scanlan</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=224340" target="vbulletin">Gavin Robert Clarke</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=151240" target="vbulletin">Geoff Carew</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=49413" target="vbulletin">Giovanni Martinez</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=217716" target="vbulletin">Green Cat</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=6639" target="vbulletin">Hanafi Jamil</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=168794" target="vbulletin">Hani Saad</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=143597" target="vbulletin">Hanson Wong</a>,
	Hartmut Voss,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=163326" target="vbulletin">Ivan Anfimov</a>,
	Jacquii Cooke,
	Jan Allan Zischke,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=179020" target="vbulletin">Jaume L&oacute;pez</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=87479" target="vbulletin">Jelle Van Loo</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=234854" target="vbulletin">Jen Rundell</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=60433" target="vbulletin">Jeremy Dentel</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=24628" target="vbulletin">Jerry Hutchings</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=264173" target="vbulletin">Joan Gauna</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=272640" target="vbulletin">Joe Rosenblum</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=8481" target="vbulletin">Joe Velez</a>,
	Joel Young,
	John Jakubowski,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=1" target="vbulletin">John Percival</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=221809" target="vbulletin">Jonathan Javier Coletta</a>,
	Joseph DeTomaso,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=287901-Kevin-Connery" target="vbulletin">Kevin Connery</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=2751" target="vbulletin">Kevin Schumacher</a>,
	Kevin Wilkinson,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=1034" target="vbulletin">Kier Darby</a>,
	Kira Lerner,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=62022" target="vbulletin">Kolby Bothe</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=33824" target="vbulletin">Lisa Swift</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=234985" target="vbulletin">Marco Mamdouh Fahem</a>,
	<a href="http://www.famfamfam.com/lab/icons/silk/" target="vbulletin">Mark James</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=43043" target="vbulletin">Martin Meredith</a>,
	Matthew Gordon,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=21333" target="vbulletin">Merjawy</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=44899" target="vbulletin">Mert Gokceimam</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=48125" target="vbulletin">Michael Biddle</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=113346" target="vbulletin">Michael Fara</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=266078" target="vbulletin">Michael Henretty</a>,
	Michael Kellogg,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=4519" target="vbulletin">Michael \'Mystics\' K&ouml;nig</a>,
	Michael Pierce,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=292167" target="vbulletin">Michlerish</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=37" target="vbulletin">Mike Sullivan</a>,
	Milad Kawas Cale,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=85376" target="vbulletin">miner</a>,
	Nathan Wingate,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=262703" target="vbulletin">nickadeemus2002</a>,
	Ole Vik,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=219" target="vbulletin">Overgrow</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=106836" target="vbulletin">Peggy Lynn Gurney</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=262025" target="vbulletin">Prince Shah</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=260829" target="vbulletin">Pritesh Shah</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=314788" target="vbulletin">Priyanka Porwal</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=166185" target="vbulletin">Pieter Verhaeghe</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=137708" target="vbulletin">Riasat Al Jamil</a>,
	Robert Beavan White,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=130915" target="vbulletin">Roms</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=39584" target="vbulletin">Ryan Ashbrook</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=96188" target="vbulletin">Ryan Royal</a>,
	Sal Colascione III,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=1814" target="vbulletin">Scott MacVicar</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=626" target="vbulletin">Scott Molinari</a>,
	Scott William,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=1121" target="vbulletin">Scott Zachow</a>,
	Shawn Vowell,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=268290" target="vbulletin">Sophie Xie</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=5374" target="vbulletin">Stephan \'pogo\' Pogodalla</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=91332" target="vbulletin">Sven "cellarius" Keller</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=301658" target="vbulletin">Tariq Bafageer</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=46384" target="vbulletin">The Vegan Forum</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=61340" target="vbulletin">ThorstenA</a>,
	Tom Murphy,
	Tony Phoenix,
	<a href="http://www.vikjavev.com/hovudsida/umtestside.ph' . 'p" target="vbulletin">Torstein H&oslash;nsi</a>,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=262689" target="vbulletin">Tully Rankin</a>,
	Vinayak Gupta,
	<a href="http://www.vbulletin.com/forum/member.ph' . 'p?u=38627" target="vbulletin">Yves Rigaud</a>
	', '', 'top', NULL, false);
print_label_row('<b>' . $vbphrase['copyright_enforcement_by'] . '</b>', '
	<a href="http://www.vbulletin.com/" target="vbulletin">vBulletin Solutions, Inc.</a>
', '', 'top', NULL, false);
print_table_footer();

/*======================================================================*\
|| ####################################################################
|| # Downloaded: 01:57, Mon Sep 12th 2011
|| # CVS: $RCSfile$ - $Revision: 44909 $
|| ####################################################################
\*======================================================================*/
?>
