// Рейтинг звёзд с автовыбором пропущенных
document.addEventListener('DOMContentLoaded', function() {
    const stars = document.querySelectorAll('#reviewModal .star');
    const hiddenInput = document.getElementById('rating');

    stars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = parseInt(this.getAttribute('data-rating'));

            // Выделяем все звёзды до выбранной
            stars.forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('yes');
                } else {
                    s.classList.remove('yes');
                }
            });

            // Сохраняем значение
            hiddenInput.value = rating;
        });

        // Подсветка при наведении
        star.addEventListener('mouseover', function() {
            const hoverRating = parseInt(this.getAttribute('data-rating'));
            stars.forEach((s, index) => {
                s.classList.toggle('hover', index < hoverRating);
            });
        });

        star.addEventListener('mouseout', function() {
            stars.forEach(s => s.classList.remove('hover'));
        });
    });
      // === СБРОС РЕЙТИНГА ПРИ ЗАКРЫТИИ МОДАЛЬНОГО ОКНА ===
    const modal = document.getElementById('reviewModal');
    modal.addEventListener('hidden.bs.modal', function() {
        stars.forEach(star => star.classList.remove('yes')); // Убираем выделение звёзд
        hiddenInput.value = '0'; // Сбрасываем значение рейтинга
    });
});





// Блок rate-box
// делаем его неактивным и берем данные из модального окна reviewModal


document.addEventListener('DOMContentLoaded', function() {

    const savedRating = localStorage.getItem('userRating');
    if (savedRating) {
        updateRateBox(savedRating);
    }
    // Обработка звёзд в модальном окне
    const modalStars = document.querySelectorAll('#reviewModal .star');
    modalStars.forEach(star => {
        star.addEventListener('click', function() {
            const rating = this.getAttribute('data-rating');
            document.getElementById('rating').value = rating;

            // Обновляем звёзды в модальном окне
            modalStars.forEach((s, i) => {
                if (i < rating) {
                    s.classList.add('yes');
                } else {
                    s.classList.remove('yes');
                }
            });

            // Обновляем оценку в rate-box
            updateRateBox(rating);
        });
    });

    // Функция для обновления блока rate-box
    function updateRateBox(rating) {
        const rateBoxStars = document.querySelectorAll('.rate-box .star');
        const rateBoxValue = document.querySelector('.rate-box h5 span span:last-child');

        // Обновляем числовое значение
        rateBoxValue.textContent = rating;

        // Обновляем звёзды
        rateBoxStars.forEach((star, i) => {
            if (i < rating) {
                star.classList.add('yes');
            } else {
                star.classList.remove('yes');
            }
        });
        localStorage.setItem('userRating', rating);

    }

    // Делаем звёзды в rate-box неинтерактивными
    const rateBoxStars = document.querySelectorAll('.rate-box .star');
    rateBoxStars.forEach(star => {
        star.style.pointerEvents = 'none';
    });
});


