/* Show loading overlay */
function loading(load) {
	load = typeof load == 'undefined' ? true : load
	if(load) {
		$('#loading').show();
	}
	else {
		$('#loading').hide();
	}
}
// bind keys to visualizer
$(document).keydown(function(e){
	if(Jmol) { 
		switch(e.which) {
//			case 37: // left

//			break;

			case 38: // up
			showPrevConformation();
			break;

//			case 39: // right

//			break;

			case 40: // down
			showNextConformation();
			break;

			default: return; // exit this handler for other keys
		}
		e.preventDefault();
	}
});

// query form vars
$('.query_delete_var').click(function() {
	if($(this).parent('div').parent('div').children('div.query_var').length > 1) {
		var container = $(this).parent('div').parent('div');
		$(this).parent('div').slideUp('fast',function(){
			$(this).remove();
			var first = container.children('div.query_var').eq(0).children('select[name^="var_logic"]').eq(0)
			first.css('visibility','hidden')
			first.children().removeAttr("selected");
			first.val("AND");
		});
		
	}
	else {
		$(this).parent('div').children('select[name^="var"]').each(function() { 
			$(this).removeAttr('selected');
			$(this).children().removeAttr("selected");
		});
		$(this).parent('div').children('input[name^="var"]').each(function() { 
			$(this).val('');
		});
	}
});
$('.query_add_var').click(function() {
	var new_var = $(this).parent().clone(true)
	new_var.hide().insertAfter($(this).parent()).slideDown('fast')
	new_var.children('select[name^="var_logic"]').eq(0).css('visibility','')
});
// query form targets
$('.query_delete_target').click(function() {
	if($('div.container_target').length > 1) {
		$(this).parent('div').parent('div').slideUp('fast',function(){ 
			$(this).remove();
			$('.container_target').first().map(function() {
				var first = $('select[name^="var_target_logic"]', this).first()
				first.css('visibility','hidden')
				first.children().removeAttr("selected");
			});
			// show "Any target"
			if($('div.container_target').length == 1) {
				$('select[name="var_target[]"] > option[value="-1"]').show();
			}
		 });
	}
	else {
		
		$(this).parent('div').parent('div').map(function() {
			$('select[name^="var_target"]', this).children().removeAttr("selected");
			// remove all but first query var and clean it's value
			$('div.query_var:not(:first)', this).slideUp('fast',function(){ 
				$(this).remove();
			});
			$('select[name^="var"]', this).each(function() { 
				$(this).removeAttr('selected');
				$(this).children().removeAttr("selected");
			});
			$('input[name^="var"]', this).each(function() { 
				$(this).val('');
			});
			// show "Any target"
			$('select[name="var_target[]"] > option[value="-1"]').show();
		});
		
	}
	
});
$('.query_add_target').click(function() {
	var new_target = $('div.container_target').last().clone(true);
	new_target.hide().insertAfter($('div.container_target').last()).slideDown('fast');
	$('select[name^="var_target_logic"]', new_target).eq(0).css('visibility','');
	// renumber vars
	var i = 0;
	$('div.container_var', $('div.container_target')).each(function() {
		$('select[name^="var"], input[name^="var"]', $(this)).each(function() { 
			$(this).attr('name', $(this).attr('name').replace(/\[(\d+)\]/g, '['+ i +']'))
		});
		i = i + 1;
	});
	// Hide "Any target" from view
	if($('div.container_target').length > 1) {
		$('select[name="var_target[]"] > option[value="-1"]').each(function() {
			$(this).hide()
			if($(this).attr('selected') || $(this).parent().val() == "-1") {
				$(this).removeAttr('selected');
				$(this).parent().map(function() {
					$(this).val($(this).children(':not([value=-1])').first().val());
				});
			}
		});
		$('select[name="var_target[]"]').trigger('change');
	}
});

$('select[name="var_target[]"]').change(function() {
	if($('div.container_target').length > 1) {
		$('select[name="sort_target"] > option:not([value=""])').each(function() {
			$(this).hide();
		});
		$('select[name="var_target[]"]').each(function() {
			$('select[name="sort_target"] > option[value="' + $(this).val() +'"]').show();
		});
		
	}
	else {
		$('select[name="sort_target"] > option').each(function() {
			$(this).show();
		});
	}
});

$('input:checkbox[name="disable_mol_grouping"]').change(function() {
	if($(this).is(":checked")) {
		$('select[name="sort_target"]').map(function() {
			$(this).prop('disabled', 'disabled')
			$(this).val("")
			$(this).children().removeAttr("selected");
		});
	}
	else {
		$('select[name="sort_target"]').prop('disabled', false);
	}
});
// rankscore targets
$('.rankscore_delete_target').click(function() {
	if($('ul.container_target').children('li').length > 1) {
		$(this).parent('li').slideUp('fast',function(){ 
			$(this).remove();
			// show "Any target"
			if($('ul.container_target').children('li').length == 1) {
				$('select[name^="target_id"] > option[value=""]', $('ul.container_target').children('li')).show();
			}
		 });
	}
	else {
		
		$(this).parent('li').map(function() {
			$('select[name^="target_id"]', $(this)).children().removeAttr("selected");
			// show "Any target"
			$('select[name^="target_id"] > option[value=""]', $(this)).show();
		});
		
	}
	
});
$('.rankscore_add_target').click(function() {
	var new_target = $('ul.container_target').children('li').last().clone(true);
	new_target.hide().insertAfter($('.container_target').children('li').last()).slideDown('fast');
	// Hide "Any target" from view
	if($('ul.container_target').children('li').length > 1) {
		$('select[name="target_id[]"] > option[value=""]', $('ul.container_target')).each(function() {
			$(this).hide()
			if($(this).attr('selected') || $(this).parent().val() == "") {
				$(this).removeAttr('selected');
				$(this).parent().map(function() {
					$(this).val($(this).children(':not([value=""])').first().val());
				});
			}
		});
		$('select[name="target_id[]"]', $('ul.container_target')).trigger('change');
	}
});


// data chart series
$('.delete-series').click(function() {
	if($('.series').length > 1) {
		$(this).parent().slideUp('fast',function(){ 
			$(this).remove();
		});
	}
});
$('#add-series').click(function() {
	$('#series-container').append($('.series').last().clone(true))
});

// notify on JSME structural change
function JSME_update() {
	$('#smiles').val(document.JSME.smiles());
}

// notify on smiles change
$('#smiles-clean').click(function() {
	convert_smi2sdf($('#smiles').val())
});

// convert SMILES using NCI REST
function convert_smi2sdf(smiles) {
	$.ajax({
		url: "openbabel_ajax.php",
		crossDomain: true,
		data: {
		  "smiles" : encodeURIComponent(smiles),
		  "output" : "sdf"
		},
		success: function( data ) {
			document.JSME.readMolFile(data);
		},
		error: function(jqXHR, textStatus, errorThrown) {
			("Ajax error: " + errorThrown);
		}
	 });
}

// draw JSME
$('#collapseOne').on('shown', function() {
	if(!document.JSME) {
		drawJsme()
	}
});

// invert selection button
$('input[name^="invert"]').click(function() {
	$('input[name="'+$(this).attr('name').replace('invert-', '')+'"]').each(function () {
		$(this).attr('checked', !$(this).attr('checked'));
	});
});
// select all button
$('input[name^="selall"]').click(function() {
	if($(this).is(':checkbox')) {
		var get_selection = $(this).prop('checked');
	}
	else {
		var get_selection = !$('input[name="'+$(this).attr('name').replace('selall-', '')+'"]').first().prop('checked');
	}
	$('input[name^="'+$(this).attr('name').replace('selall-', '')+'"]').each(function () {
		$(this).prop('checked', get_selection);
	});
});

// selection form downloads


$('input[type="checkbox"]', $('form[name="selection-form"]')).click(function() {
	if($('input:checked', $('form[name="selection-form"]')).length > 0) {
		$('ul.selected-download').css('display', ''),
		$('ul.selected-download').parent('li').removeClass('disabled');
	}
	else {
		$('ul.selected-download').hide();
		$('ul.selected-download').parent('li').addClass('disabled');
	}
});

// highlight rows
$('table.molecules tr').hover(function() {
	$(this).prevUntil('tr:not(.'+$(this).attr('class')+')').last().children('td[rowspan]').each(function() {
		$(this).addClass("hovered");
	});
}, function () {
	$(this).prevUntil('tr:not(.'+$(this).attr('class')+')').last().children('td[rowspan]').each(function() {
		$(this).removeClass("hovered");
	});
});

// click on row to select
$('table.molecules tr').click(function() {
	if(event.target.type != 'checkbox' && event.target.type != 'button') {
		// click on self
		$(this).children().children('input:checkbox').first().each(function() { $(this).attr('checked', !$(this).attr('checked')) })
		// click on spanned
		$(this).prevUntil('tr:not(.'+$(this).attr('class')+')').last().children().children('input:checkbox').first().each(function() { $(this).attr('checked', !$(this).attr('checked')) })
	}
});

// resize molecules images on hover
$("img.mol").hover(function() {
	$(this).stop(true,true).delay(1000).animate({
		width: 300,
		height: 300,
	});
}, function() {
	$(this).stop(true,true).delay(1000).animate({
		width: 100,
		height: 100,
	});
});

// binding profile
$('div.binding_profile_residue').hover(function() {
	//$(this).animate( { "opacity" : .3 }, 50);
	$(this).stop(true,true).animate( { "opacity" : .3 }, 50).delay(500).queue(function() {
		if($(this).attr('id')) {
			script = [	'select 1.1 resno = ' + $(this).attr('id'),
					'wireframe 50'];
			Jmol.script(jmolApplet0,script.join("; "));
		}
	});
}, function() {
	if($(this).queue().length > 1) {
		$(this).stop(true,true).animate( { "opacity" : 1 }, 50);
	}
	else {
		$(this).stop(true,true).animate( { "opacity" : 1 }, 50).delay(100).queue(function() {
			if($(this).attr('id')) {
				script = [	'select 1.1 resno = ' + $(this).attr('id'),
						'wireframe'];
				Jmol.script(jmolApplet0,script.join("; "));
			}
		});
	}
});

$('button.binding_profile_preview').click(function() {
	if(!$(this).hasClass('btn-success') && $('button.binding_profile_preview.btn-success').length > 0) {
		$('button.binding_profile_preview.btn-success').trigger('click');
	}
	ires = [];
	$('div.binding_profile_residue', $(this).parent()).each(function() {
		if($(this).children('div.interaction').length > 0) {
			if($(this).attr('id')) {
				ires.push($(this).attr('id'));
			}
		}
	});
	script = ['select 1.1 and (resno = ' + ires.join(', resno = ') + ')'];
	if($(this).hasClass('btn-success')) {
		script.push('wireframe');
		script.push('color darkblue');
		$(this).removeClass('btn-success').removeClass('active');
	}
	else {
		script.push('color cpk');
		script.push('wireframe 30');
		//change color of rest of the protein
		script.push('select 1.1 and not selected; color darkblue');
		$(this).addClass('btn-success').addClass('active');
		
	}
	Jmol.script(jmolApplet0,script.join("; "));
	// check if any binding profile is active and return to CPK color scheme
	if($('button.binding_profile_preview.btn-success').length == 0) {
		Jmol.script(jmolApplet0,'select; color cpk;')
	}
	
});

$('input[name="crude-checkbox"]').click(function() {
	if($(this).is(":not(:checked)")) {
		$('div.crude').show();
	}
	else {
		$('div.crude').hide();
	}
});

// reset selection on acordion colapse
$('div.accordion-body').each(function () {
	$(this).on('hidden', function () {
		$('input:not(input[type=button],input[type=submit],button), select', this).each(function() {
			$(this).val('');
	  		$(this).removeAttr('selected');
	  	});
	});
});
