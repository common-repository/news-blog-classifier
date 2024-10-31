function nbc_get_classify(){
	document.getElementById("nbc_content").innerHTML =  "";
	var timerId = 0;
	var ctr=0;
	var maxValue=78;
	
	var text_content_clean = nbc_get_text_content().replace(/<\/?([a-z][a-z0-9]*)\b[^>]*>/gi, "");
	
	if(text_content_clean != ''){
		jQuery("#nbc_progress_bar").show();
		jQuery("#nbc_progress_bar").progressbar({
			value: false,
			max: 80,
			change: function() {
				jQuery("#progress-label").text(jQuery("#wca_progress_bar").progressbar( "value" ) + "% complete");
			},
			complete: function(event, ui) {
				jQuery(".progress-label").text( "Process Complete!" );
				jQuery("#nbc_progress_bar").hide();
			}
		});
		jQuery(".progress-label").text( "Defining Category... Please wait." );
		
		jQuery.ajax({
			  type:"POST",
			  url: ajax_object.ajax_url,
			  data: {
				  text_content: nbc_get_text_content(),
				  security: ajax_object.ajax_meta_box_nonce,
				  action: "nbc_define_category",
			  },
			  success:function(data){
				if(data){
					jQuery("#nbc_progress_bar").progressbar({
						value: 100
					});
					var sentence = "<div sytle='display:inline;'><p style='font-size:15px;'>Category: &nbsp;&nbsp;<button style='background-color:transparent;outline-color: #b3cef9;outline-style: solid;'>";
					var category_name = jQuery.parseJSON(data);
					
					document.getElementById("nbc_content").innerHTML =  "<div id='result_category' sytle='display:inline;'><p style='font-size:15px;'>Category: &nbsp;&nbsp;<button readonly type='button' style='background-color:transparent;outline-color: #b3cef9;outline-style: solid;'>" + category_name.category_name + "</button></p></div><p><a id='remove_category' href='#' onclick='nbc_remove_category();return false;'>Remove (Cancel) Category</a></p>";
					document.getElementById("nbc_new_define_category").value = category_name.category_name;
					
				}
				
			  },
			  error: function(){
				  jQuery("#nbc_progress_bar").progressbar({
					value: 100
				  });
				  document.getElementById("nbc_content").innerHTML = "<strong style='color:red;'>Ooops! Error occured! Please try again.</strong>";
			  } 

		});
		
		
		timerId = setInterval(function () {
			// interval function
			ctr++;
			jQuery("#nbc_progress_bar").progressbar({
				value: ctr
			});
			
			// max reached?
			if (ctr==maxValue){
			  clearInterval(timerId);
			}
			
		 }, 1000);
	}
	else{
		document.getElementById("nbc_content").innerHTML = 'Content post is empty!'
	}
	
}

function nbc_get_text_content() {
	if (typeof tinyMCE != 'undefined' && tinyMCE.activeEditor != null && tinyMCE.activeEditor.isHidden() == false) {
		return tinyMCE.activeEditor.getBody().innerHTML;
	}
	return document.getElementById('content').value;
}

function nbc_remove_category(){
	document.getElementById("nbc_content").innerHTML = "";
	document.getElementById("nbc_new_define_category").value = "";
}