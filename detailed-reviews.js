document.addEventListener('DOMContentLoaded', function () {

	function rateIt(el, cat) {
		let rating = parseInt(el.title);
		document.getElementById(cat + '_rating').value = rating;
		for (let i = 1; i <= 5; i++) {
			let star = document.getElementById(cat + '_' + i);
			star.className = (i <= rating) ? 'fa-solid fa-star selected-star' : 'fa-regular fa-star';
		}
		return false;
	}

	function rating(el, cat) {
		let hover = parseInt(el.title);
		for (let i = 1; i <= 5; i++) {
			let star = document.getElementById(cat + '_' + i);
			star.className = (i <= hover) ? 'fa-solid fa-star hover-star' : 'fa-regular fa-star';
		}
		return false;
	}

	function rolloff(el, cat) {
		let saved = parseInt(document.getElementById(cat + '_rating').value);
		for (let i = 1; i <= 5; i++) {
			let star = document.getElementById(cat + '_' + i);
			star.className = (i <= saved) ? 'fa-solid fa-star selected-star' : 'fa-regular fa-star';
		}
		return false;
	}

	// expose globally for inline onclick=""
	window.rateIt = rateIt;
	window.rating = rating;
	window.rolloff = rolloff;

	console.log('✅ Detailed Reviews JS loaded and handlers assigned');

});
