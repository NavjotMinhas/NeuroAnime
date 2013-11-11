// fixed by -pixy-


/*
* jQuery Floatbox Plugin 1.0.3
* Copyright (c) 2008 Leonardo Rossetti (motw.leo@gmail.com)
* Licensed under the MIT license (http://www.opensource.org/licenses/mit-license.php)
* THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
* IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
* FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
* AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
* LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
* OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
* THE SOFTWARE.
*/
(function ($) {
    $.floatbox = function (options) {
        var settings = $.extend({
            bg : "floatbox-background",
            box : "floatbox-box",
            content : "",
			button: "<p><a role='button' href='javascript:void(0);' class='close-floatbox'>Close</a></p>",
			desc: "This is a popup box, press esc key to close.",
            fade : false,
			ajax: null,
            bgConfig : {
                position: ($.browser.msie) ? "absolute" : "fixed",
                zIndex: 998,
                width: "100%",
                height: "100%",
                top:  "0px",
                left: "0px",
                backgroundColor: "#000",
                opacity: "0.75",
                display: "none"
            },
            boxConfig : {
                position : ($.browser.msie) ? "absolute" : "fixed",
                zIndex: 999,
                width: ($(window).width() * 0.6) + "px",
                marginLeft: "-" + ($(window).width() * 0.3) + "px",
                height: "auto",
                top: "50%",
                left: "50%",
                backgroundColor: "#fff",
                display: "none"
            }
        }, options);

        //inserts floatbox and sets its content
        var showBox = function () {
			var content = typeof settings.content === "string" ? settings.content : settings.content.clone();
            //inserts the background element in the document
            $("<div></div>")
                .bind("click", function () {
                    closeBox();
                })
                .attr("id", settings.bg)
                .css(settings.bgConfig)
                .width(($.browser.msie) ? document.body.clientWidth : "100%")
                .height(($.browser.msie) ? document.body.clientHeight : "100%")
                .appendTo("body");
            //inserts the floating box in the document
            $("<div></div>")
                .attr({id: settings.box, role: "alertdialog"}) 
                .html(content)
                .append(settings.button)
                .css(settings.boxConfig)
                .appendTo("body")
                .css("margin-top", "-" + $("#" + settings.box).height() / 2 + "px")
                .find(".close-floatbox").bind("click", function () {
                    closeBox();
                })
                .end();
            //checks if it needs to fade or not
            if (settings.fade) {
                $("#" + settings.bg)
                .fadeIn(200, function () {
                    $("div#" + settings.box).fadeIn(200);
                });
            } else {
                $("#" + settings.bg)
                .show()
                .parent().find("#" + settings.box).show();
            }
			//sets if ajax is needed(already detectets if it is POST or GET)
			if (settings.ajax) {
				$.ajax({
					type: settings.ajax.params === "" ? "GET" : "POST",
					url: settings.ajax.url,
					data: settings.ajax.params,
					
					beforeSend: function () {
						$("#" + settings.box).html(settings.ajax.before);
					},
					
					success: function (data) {
						$("#" + settings.box)
							.html(data)
							.append(settings.button)
							.find(".close-floatbox").bind("click", function () {
								closeBox();
							});
					},
					contentType: "html"
				});
			}
        };
        //hides floatingbox and background
        var closeBox = function () {
            if (settings.fade) {
                $("#" + settings.box).fadeOut(200, function () {
                     $("#" + settings.bg).fadeOut(200, function () {
						$("#" + settings.box).remove();
						$("#" + settings.bg).remove();
                    });
                });
            } else {
				//for opera issues hide first and a timeout is needed to remove the elements
				$("#" + settings.box + ",#" + settings.bg).hide();
				setTimeout(function () {
					$("#" + settings.box).remove();
					$("#" + settings.bg).remove();
				}, 500);
            }
        };
        //inits the floatbox
        var init = function () {
// fix
			$("#" + settings.box).remove();
			$("#" + settings.bg).remove();
// end
            //shows box
            showBox();
			//adds cross browser event to esc key to hide floating box
            $(document).one("keypress", function (e) {
                var escKey = $.browser.mozilla ? 0 : 27;
                if (e.which === escKey) {
                    closeBox();
                }
            });
			//if msie6, adds event to browser scroll to keep floatbox ina fixed position and uses css hack for full background size
	        if ($.browser.msie) {
	            $("body, html").css({height: "100%", width: "100%"});
	            $(window).bind("scroll", function () {
	                $("#" + settings.box).css("top", document.documentElement.scrollTop +  ($(window).height() / 2) + "px");
	            });
	        }
        };
        //starts the plugin
        init();
    };
})(jQuery);