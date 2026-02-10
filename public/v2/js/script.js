const showRooms = document.querySelectorAll('.show-room-old')

for (let showRoom of showRooms) {
    showRoom.addEventListener('click', ()=> {
        showRoom.classList.toggle('open');
    });
}

const listTags = document.querySelectorAll('.list-group.tags .list-group-item')

for (let listTag of listTags) {
    listTag.addEventListener('click', ()=> {
        listTag.classList.add('d-none');
    });
}

const rateStars = document.querySelectorAll('.rate-stars .star');

for (let star of rateStars) {
    star.addEventListener('click', ()=> {
        star.classList.toggle('yes');
    });
}

var slimSelectSettings = {
    placeholderText: 'Выберите специализацию',
    searchPlaceholder: 'Поиск',
    searchText: 'Ничего не найдено'
};
var selectSpecialization = document.querySelector('#selectElement') || document.querySelector('#selectElementBuilders');
if (selectSpecialization) {
    new SlimSelect({
        select: selectSpecialization,
        settings: slimSelectSettings
    });
}

$(document).ready(function () {
    $('.input_tel').mask('+7 (000) 000-00-00');
});
