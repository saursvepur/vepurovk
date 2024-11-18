document.addEventListener('DOMContentLoaded', function() {
  const deadline = new Date('jan 01, 2024 00:00:00');
  let timerId = null;
  // склонение блядских числительных
  function declensionNum(num, words) {
    return words[(num % 100 > 4 && num % 100 < 20) ? 2 : [2, 0, 1, 1, 1, 2][(num % 10 < 5) ? num % 10 : 5]];
  }
  // вычисляем ебучую разницу дат и завершаем новым годом. функция спизжена
  function countdownTimer() {
    const diff = deadline - new Date();
    if (diff <= 0) {
      clearInterval(timerId);
	  document.querySelector('.timer__items').className = "hidden";
	  document.querySelector('.timer__days').className = "hidden";
	  document.querySelector('.timer__hours').className = "hidden";
	  document.querySelector('.timer__minutes').className = "hidden";
	  document.querySelector('.timer__seconds').className = "hidden";
	  document.querySelector('.new_year_coming').className = "hidden";
	  document.querySelector('.timer__result') .textContent = 'С Новым Годом!';
    }
    const days = diff > 0 ? Math.floor(diff / 1000 / 60 / 60 / 24) : 0;
    const hours = diff > 0 ? Math.floor(diff / 1000 / 60 / 60) % 24 : 0;
    const minutes = diff > 0 ? Math.floor(diff / 1000 / 60) % 60 : 0;
    const seconds = diff > 0 ? Math.floor(diff / 1000) % 60 : 0;
    $days.textContent = days < 10 ? + days : days;
    $hours.textContent = hours < 10 ? + hours : hours;
    $minutes.textContent = minutes < 10 ? + minutes : minutes;
    $seconds.textContent = seconds < 10 ? + seconds : seconds;
  }
  // выводим ебучие минуты и всю хуету
  const $new_year_coming = document.querySelector('.new_year_coming');
  const $days = document.querySelector('.timer__days');
  const $hours = document.querySelector('.timer__hours');
  const $minutes = document.querySelector('.timer__minutes');
  const $seconds = document.querySelector('.timer__seconds');
  // вызываем функцию countdownTimer которая и выводит эту хуету
  countdownTimer();
  // вызываем функцию countdownTimer каждую секунду делает пук
  timerId = setInterval(countdownTimer, 1000);
});