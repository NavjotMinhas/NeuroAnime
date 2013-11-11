<script type="text/javascript">
	function setCurrent(id){
		var array=document.getElementsByTagName('ul');
		for (var i=0;i<array.length;i++){
			if(array[i].getAttribute('class')=='current'){
				array[i].setAttribute('class', 'select');
			}
		}
		var element = document.getElementById(id);
		element.setAttribute('class', 'current');
		
	}
</script>
<!--  start nav-outer-repeat................................................................................................. START -->

<div class="nav-outer-repeat">
  <!--  start nav-outer -->
  <div class="nav-outer">

    <!-- start nav-right -->
    <div id="nav-right">
      <div class="nav-divider">&nbsp;</div>
      <div class="showhide-account"><img src="images/shared/nav/nav_myaccount.gif" width="93" height="14" alt="" /></div>
      <div class="nav-divider">&nbsp;</div>
      <a href="" id="logout"><img src="images/shared/nav/nav_logout.gif" width="64" height="14" alt="" /></a>
      <div class="clear">&nbsp;</div>
      <!--  start account-content -->
      <div class="account-content">
        <div class="account-drop-inner"> <a href="" id="acc-settings">Settings</a>
          <div class="clear">&nbsp;</div>
          <div class="acc-line">&nbsp;</div>
          <a href="" id="acc-details">Personal details </a>
          <div class="clear">&nbsp;</div>
          <div class="acc-line">&nbsp;</div>
          <a href="" id="acc-project">Project details</a>
          <div class="clear">&nbsp;</div>
          <div class="acc-line">&nbsp;</div>
          <a href="" id="acc-inbox">Inbox</a>
          <div class="clear">&nbsp;</div>
          <div class="acc-line">&nbsp;</div>
          <a href="" id="acc-stats">Statistics</a> </div>
      </div>
      <!--  end account-content -->
    </div>
    <!-- end nav-right -->
    <!--  start nav -->
    <div class="nav">
      <div class="table">
        <ul id="adminboard" onclick="setCurrent('adminboard')" class="select">
          <li><a href="#nogo"><b>Admin Messageboard</b>
            <!--[if IE 7]><!--></a><!--<![endif]-->
            <!--[if lte IE 6]><table><tr><td><![endif]-->
            <div class="select_sub show">
              <ul class="sub">
                <li><a href="index.php">Admin Board</a></li>
                <li><a href="addmessage.php">Add Message</a></li>
              </ul>
            </div>
            <!--[if lte IE 6]></td></tr></table></a><![endif]-->
          </li>
        </ul>
        <div class="nav-divider">&nbsp;</div>
        <ul id="slider" onclick="setCurrent('slider')" class="select">
          <li><a href="#nogo"><b>Slider</b>
            <!--[if IE 7]><!--></a><!--<![endif]-->
            <!--[if lte IE 6]><table><tr><td><![endif]-->
            <div class="select_sub show">
              <ul class="sub">
                <li><a href="slider.php">Slider</a></li>
                <li><a href="addslider.php">Add Slider</a></li>
              </ul>
            </div>
            <!--[if lte IE 6]></td></tr></table></a><![endif]-->
          </li>
        </ul>
        <div class="nav-divider">&nbsp;</div>
        <ul id="anime" onclick="setCurrent('anime')" class="select">
          <li><a href="#nogo"><b>Anime</b>
            <!--[if IE 7]><!--></a><!--<![endif]-->
            <!--[if lte IE 6]><table><tr><td><![endif]-->
            <div class="select_sub show">
              <ul class="sub">
                <li><a href="addanime.php">Add Anime</a></li>
                <li><a href="addepisode.php">Add Episode</a></li>
                <li><a href="removeepisode.php">Remove Episode</a></li>
              </ul>
            </div>
            <!--[if lte IE 6]></td></tr></table></a><![endif]-->
          </li>
        </ul>
        <div class="nav-divider">&nbsp;</div>
        <ul  id="link" onclick="setCurrent('link')" class="select">
          <li><a href="#nogo"><b>Link</b>
            <!--[if IE 7]><!--></a><!--<![endif]-->
            <!--[if lte IE 6]><table><tr><td><![endif]-->
            <div class="select_sub show">
              <ul class="sub">
                <li><a href="linkmoderator.php">Link Moderations</a></li>
              </ul>
            </div>
            <!--[if lte IE 6]></td></tr></table></a><![endif]-->
          </li>
        </ul>
        <div class="nav-divider">&nbsp;</div>
        <div class="clear"></div>
      </div>
      <div class="clear"></div>
    </div>
    <!--  start nav -->
  </div>
  <div class="clear"></div>
  <!--  start nav-outer -->
</div>
<!--  start nav-outer-repeat................................................... END -->
