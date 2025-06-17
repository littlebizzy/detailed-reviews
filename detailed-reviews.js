function rateIt(el, cat) {
	let rating = parseInt(el.title);
	document.getElementById(cat + '_rating').value = rating;
	for (let i = 1; i <= 5; i++) {
		let star = document.getElementById(cat + '_' + i);
		star.classList.remove('fa-regular');
		star.classList.remove('fa-solid');
		star.classList.add(i <= rating ? 'fa-solid' : 'fa-regular');
	}
	return false;
}

function rating(el, cat) {
	let hover = parseInt(el.title);
	for (let i = 1; i <= 5; i++) {
		let star = document.getElementById(cat + '_' + i);
		star.classList.remove('fa-regular');
		star.classList.remove('fa-solid');
		star.classList.add(i <= hover ? 'fa-solid' : 'fa-regular');
	}
	return false;
}

function rolloff(el, cat) {
	let saved = parseInt(document.getElementById(cat + '_rating').value);
	for (let i = 1; i <= 5; i++) {
		let star = document.getElementById(cat + '_' + i);
		star.classList.remove('fa-regular');
		star.classList.remove('fa-solid');
		star.classList.add(i <= saved ? 'fa-solid' : 'fa-regular');
	}
	return false;
}
