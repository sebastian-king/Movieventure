//$(document).ready(function() {
	/*
    $('#add-form-bv-wz').bootstrapWizard({
        tabClass		    : 'wz-steps',
        nextSelector	    : '.next',
        previousSelector	: '.previous',
        onTabClick          : function(tab, navigation, index) {
            return false;
        },
        onInit : function(){
            $('#add-form-bv-wz').find('.finish').hide().prop('disabled', true);
        },
        onTabShow: function(tab, navigation, index) {
            var $total = navigation.find('li').length;
            var $current = index+1;
            var $percent = ($current/$total) * 100;
            var wdt = 100/$total;
            var lft = wdt*index;
            $('#add-form-bv-wz').find('.progress-bar').css({width:wdt+'%',left:lft+"%", 'position':'relative', 'transition':'all .5s'});
            if($current >= $total) {
                $('#add-form-bv-wz').find('.next').hide();
                $('#add-form-bv-wz').find('.finish').show();
                $('#add-form-bv-wz').find('.finish').prop('disabled', false);
            } else {
                $('#add-form-bv-wz').find('.next').show();
                $('#add-form-bv-wz').find('.finish').hide().prop('disabled', true);
            }
        },
        onNext: function(){
            isValid = null;
            $('#add-form-bv-wz-form').bootstrapValidator('validate');
            if(isValid === false)return false;
        }
    });
	*/

    /* var isValid;
    $('#add-form-bv-wz-form').bootstrapValidator({
        message: 'This value is not valid',
        feedbackIcons: {
        valid: 'fa fa-check-circle fa-lg text-success',
        invalid: 'fa fa-times-circle fa-lg',
        validating: 'fa fa-refresh'
        },
        fields: {
		moviename: {
			message: 'The title is not valid.',
            validators: {
                notEmpty: {
                    message: 'A title is required.'
                }
            }
		},
		imdbID: {
            validators: {
                notEmpty: {
                    message: 'You must enter an IMDB ID.'
                },
                regexp: {
                    regexp: /^tt\d{7}$/,
                    message: 'The IMDB ID you entered is not valid.'
                }
            }
        },
        username: {
            message: 'The username is not valid',
            validators: {
                notEmpty: {
                    message: 'The username is required.'
                }
            }
        },
        email: {
            validators: {
                notEmpty: {
                    message: 'The email address is required and can\'t be empty'
                },
                emailAddress: {
                    message: 'The input is not a valid email address'
                }
            }
        },
        firstName: {
            validators: {
                notEmpty: {
                    message: 'The first name is required and cannot be empty'
                },
                regexp: {
                    regexp: /^[A-Z\s]+$/i,
                    message: 'The first name can only consist of alphabetical characters and spaces'
                }
            }
        },
        lastName: {
            validators: {
                notEmpty: {
                    message: 'The last name is required and cannot be empty'
                },
                regexp: {
                    regexp: /^[A-Z\s]+$/i,
                    message: 'The last name can only consist of alphabetical characters and spaces'
                }
            }
        },
        phoneNumber: {
            validators: {
                notEmpty: {
                    message: 'The phone number is required and cannot be empty'
                },
                digits: {
                    message: 'The value can contain only digits'
                }
            }
        },
        address: {
            validators: {
                notEmpty: {
                    message: 'The address is required'
                }
            }
        }
        }
    }).on('success.field.bv', function(e, data) {
        // $(e.target)  --> The field element
        // data.bv      --> The BootstrapValidator instance
        // data.field   --> The field name
        // data.element --> The field element

        var $parent = data.element.parents('.form-group');

        // Remove the has-success class
        $parent.removeClass('has-success');


        // Hide the success icon
        //$parent.find('.form-control-feedback[data-bv-icon-for="' + data.field + '"]').hide();
    }).on('error.form.bv', function(e) {
        isValid = false;
    }); */

//});
function nav_loaded_js_movie_add_js() {
	$("footer").append('<script type="text/javascript" src="/assets/bootstrap-validator/bootstrapValidator.min.js"></script>');
	$("footer").append('<script type="text/javascript" src="/assets/bootstrap-wizard/jquery.bootstrap.wizard.min.js"></script>');
	$("footer").append('<script type="text/javascript" src="/js/libgif.js"></script>');

	$('#add-form-bv-wz').bootstrapWizard({
        	tabClass                    : 'wz-steps',
        	nextSelector        : '.next',
        	previousSelector        : '.previous',
        	onTabClick          : function(tab, navigation, index) {
        	    return false;
        	},
        	onInit : function(){
        	    $('#add-form-bv-wz').find('.finish').hide().prop('disabled', true);
        	},
        	onTabShow: function(tab, navigation, index) {
        	    var $total = navigation.find('li').length;
        	    var $current = index+1;
        	    var $percent = ($current/$total) * 100;
        	    var wdt = 100/$total;
        	    var lft = wdt*index;
        	    $('#add-form-bv-wz').find('.progress-bar').css({width:wdt+'%',left:lft+"%", 'position':'relative', 'transition':'all .5s'});
        	    if($current >= $total) {
        	        $('#add-form-bv-wz').find('.next').hide();
        	        $('#add-form-bv-wz').find('.finish').show();
        	        $('#add-form-bv-wz').find('.finish').prop('disabled', false);
        	    } else {
        	        $('#add-form-bv-wz').find('.next').show();
        	        $('#add-form-bv-wz').find('.finish').hide().prop('disabled', true);
        	    }
        	},
        	onNext: function() {
			return true;
       		}
    	});

	var hash = window.location.hash;
	if (/^#t:(.+)$/.test(hash)) {
		var m = hash.match(/^#t:(.+)$/);
		var term = decodeURIComponent(m[1]);
		add_movie_search('title', term);
		$("#title-search").val(term);
	} else if (/^#i:tt\d{7}$/.test(hash)) {
		var m = hash.match(/^#i:(tt\d{7})$/);
		var term = decodeURIComponent(m[1]);
		add_movie_search('imdbid', term);
		$("#imdbid-search").val(term);
	}

	function add_movie_search(type, query) {
		$("#search-results-panel").slideUp();
		$("#search-results-spinner").slideDown();
		$("#search_results_query").text(query);
		if (type == 'imdbid') {
			window.location.hash = "i:" + query;
			$.get('/api/info.php?q=' + query, add_movie_process_search);
		} else if (type == 'title') {
			window.location.hash = "t:" + query;
			$.get('/api/search.php?q=' + query, add_movie_process_search);
		} else {
			alert('An error has occured, please refresh the page.');
		}
	}

	function add_movie_process_search(data) {
		if (typeof data !== "undefined" && typeof data.error === "undefined") {
			console.log(data);
			$("#search_result_total").text(data.length);
			$("#search-results-list").html('');
			for (var element in data) {
				var cast_list = "";
				for (var actor in data[element].cast) {
					for (var name in data[element].cast[actor]) {
						cast_list += name + ', ';
						break;
					}
				}
				cast_list = cast_list.replace(/,[ ]$/g, "");

				var hours = Math.floor( data[element].runtime / 60);
				var minutes = data[element].runtime % 60;
				var runtime_string = "";
				if (hours == 0 && minutes > 0) {
					runtime_string = minutes + " min" + ((minutes != 1) ? "s" : "") + ".";
				} else if (minutes == 0 && hours > 0) {
					runtime_string = hours + " hr" + ((hours != 1) ? "s" : "") + ".";
				} else if (hours > 0 && minutes > 0) {
					runtime_string = hours + " hr" + ((hours != 1) ? "s" : "") + ". " + minutes + " min" + ((minutes != 1) ? "s" : "") + ".";
				} else {
					runtime_string = "? hrs. ? mins.";
				}

				$("#search-results-list").append('' +
				'<li class="list-group-item list-item-lg">' +
					'<div class="sr-list-left">' +
						'<img src="' + data[element].poster + '"/>' +
					'</div>' +
					'<div class="sr-list-right">' +
                				'<div class="media-heading mar-no">' +
               						'<a class="movie-title-add-link btn-link text-lg text-semibold" href="#">' + data[element].title + '</a> <span id="title_year">(' + data[element].year + ')</span>' +
                				'</div>' +
                				'<p id="title_desc"><span class="label label-default"><i class="fa fa-comment"></i> ' + data[element].language + '</span> <vr></vr> <em>' + data[element].genre + '</em> <vr></vr> <b>' + runtime_string + '</b></p>' +
                				'<p class="text-lg">' + data[element].plot + '</p>' +
						'<p class="text-sm">' + cast_list + '</p>' +
						'<div><button class="btn btn-success btn-labeled" onClick="add_movie(\'' + data[element].title + '\', \'' + data[element].year + '\', \'' + data[element].imdbid + '\');"><i class="btn-label fa fa-plus fa-lg"></i> Add</button> <a href="http://www.imdb.com/title/tt' + data[element].imdbid + '/" target="_blank" class="btn btn-info btn-labeled"><i class="btn-label fa fa-info fa-lg"></i> More Info</a></div>' +
					'</div>' +
                		'</li>');
			}
			$("#search-results-spinner").slideUp();
			$("#search-results-panel").slideDown();
		} else {
			alert("search error");
		}
	}

	$("#content-container").on("click", "#search-for-movie-btn", function() {
		if (/^tt\d{7}$/.test($("#imdbid-search").val())) {
			// submit
			add_movie_search('imdbid', $("#imdbid-search").val());
		} else {
			if ($("#title-search").val().length) {
				// submit
				add_movie_search('title', $("#title-search").val());
			} else {
				alert('Please enter a search term or a valid IMDB ID.');
			}
		}
	});
	$("#content-container").on("keypress", "#imdbid-search", function(e) {
		if(e.which == 13) {
			add_movie_search('imdbid', $("#imdbid-search").val());
		}
	});
	$("#content-container").on("keypress", "#title-search", function(e) {
		if(e.which == 13) {
			add_movie_search('title', $("#title-search").val());
		}
	});

	window.add_movie = function(title, year, imdbid) { // imdb id does not includ the tt
		console.log("adding movie: " + imdbid);
		// handle session
		// hide the search results
		// use fancy loader to look for movies
		// prompt usr to double check
		// expert mode
		
		/* section: intelligent spinner start */
		$("#add-mv-tab2").html('' +
							   '<div class="loading_spinner_special_container1" style="display: none; text-align: center;">' +
									'<img id="special_loader1" src="/img/loading_spinner_begin.gif" rel:auto_play="0" />' +
								'</div>' +
								'<div class="loading_spinner_special_container2" style="display: none; text-align: center;">' +
									'<img id="special_loader2" src="/img/loading_spinner_middle.gif" rel:auto_play="0" />' +
								'</div>' +
								'<div class="loading_spinner_special_container3" style="display: none; text-align: center;">' +
									'<img id="special_loader3" src="/img/loading_spinner_end.gif" rel:auto_play="0" />' +
								'</div>' +
							  '');
		
		$('#add-form-bv-wz').bootstrapWizard('next');
		$('#search-results-panel').slideUp();

		window.goto_end = false;
		
		var gif1 = new SuperGif({
			gif: document.getElementById("special_loader1"),
			on_end: function(data) {
				console.log('iteration finished');
				$(".loading_spinner_special_container1").css('display', 'none');
				gif1.pause();
				$(".loading_spinner_special_container2").css('display', 'block');
				gif2.play();
			},
			progressbar_height: 0
		});
		gif1.load(function(data) {
			console.log('loaded');
			$(".loading_spinner_special_container1").css('display', 'block');
			gif1.play();
		});

		var gif2 = new SuperGif({
			gif: document.getElementById("special_loader2"),
			on_end: function(data) {
				console.log('iteration finished');
				if (window.goto_end === true) {
					$(".loading_spinner_special_container2").css('display', 'none');
					gif2.pause();
					$(".loading_spinner_special_container3").css('display', 'block');
					gif3.play();
				}
			},
			progressbar_height: 0
		});
		gif2.load(function(data) {
			console.log('loaded');
		});

		var gif3 = new SuperGif({
			gif: document.getElementById("special_loader3"),
			on_end: function(data) {
				console.log('iteration finished');
				gif3.pause();
			},
			progressbar_height: 0
		});
		gif3.load(function(data) {
			console.log('loaded');
		});
		/* section: intelligent spinner end */
		
		console.log('finding: ' + title + ' (' + year + ')');
		$.get('/api/get.php?title=' + encodeURIComponent(title) + '&year=' + encodeURIComponent(year) + '&imdbid' + encodeURIComponent(imdbid), function(data) {
			window.goto_end = true;
			// set session that's returned
			// display progress of download
		});
	}
}
