function rating(el, setnum) {
	var starIndex = parseInt(el.id.replace(setnum + "_", ''), 10);
	var container = el.parentNode;
	var stars = container.querySelectorAll('a');

	stars.forEach(function(star, index) {
		if (index < starIndex) {
			star.classList.add('hovered');
		} else {
			star.classList.remove('hovered');
		}
	});
}

function rolloff(el, setnum) {
	var container = el.parentNode;
	var stars = container.querySelectorAll('a');
	var selected = parseInt(document.getElementById(setnum + "_rating").value, 10);

	stars.forEach(function(star, index) {
		star.classList.remove('hovered');
		if (index < selected) {
			star.classList.add('selected');
		} else {
			star.classList.remove('selected');
		}
	});
}

function rateIt(el, setnum) {
	var starIndex = parseInt(el.id.replace(setnum + "_", ''), 10);
	document.getElementById(setnum + "_rating").value = starIndex;

	var container = el.parentNode;
	var stars = container.querySelectorAll('a');

	stars.forEach(function(star, index) {
		if (index < starIndex) {
			star.classList.add('selected');
		} else {
			star.classList.remove('selected');
		}
	});
}
