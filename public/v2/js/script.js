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
