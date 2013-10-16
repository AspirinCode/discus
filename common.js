/*  Viewing ligand in 3d pocket  */
/* Change clicked row's style class from 'normal' to 'clicked' */
var clicked_id = null;
var highlighted_id = null; /* highlited conformer */
var max_id = null

/* Check if query smiles is not empty */
function check_empty_field(form, field) {
	loading();
	if(field.value != "") {
		form.submit();
	}
	else {
		alert("You must supply molecule!");
		loading(false);
	}
}

function showClickedConformation(clicked_id) {
	target = document.getElementById("row-conf-" + clicked_id);
	highlited = document.getElementById("row-conf-" + highlighted_id);
	if (highlited) {
		if(highlighted_id % 2 == 0) {
			highlited.className = 'even';
		}
		else {
			highlited.className = 'odd';
		}
	}
//	if(highlighted_id) {
////		if(clicked_id > highlighted_id) {
//			target.scrollIntoView();
////		}
////		else {
////			target.scrollIntoView(false);
////		}
//		//$('#row-conf-' + clicked_id).localScroll();
//	}
	Jmol.script(jmolApplet0,'model 2.' + clicked_id);
	target.className = 'clicked';
	highlighted_id = clicked_id;
}

/* Show next or previous conformer in Jmol and highlight appropriate row */
function showNextConformation() {
	if (highlighted_id < max_id) {
		conf_num = highlighted_id + 1;
		showClickedConformation(conf_num);
		highlighted_id = conf_num;
	}
}

function showPrevConformation() {
	if (highlighted_id > 1) {
		conf_num = highlighted_id - 1;
		showClickedConformation(conf_num);
		highlighted_id = conf_num;
	}
}
