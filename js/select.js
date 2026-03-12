const trigger = document.querySelector('.select-trigger');
const options = document.querySelector('.select-options');

trigger.addEventListener('click', () => {
  options.style.display = options.style.display === 'block' ? 'none' : 'block';
});

document.querySelectorAll('.select-options li').forEach(item => {
  item.addEventListener('click', function () {
    document.querySelector('.placeholder').textContent = this.textContent;
    options.style.display = 'none';
  });
});

//    
document.addEventListener('click', function (e) {
  if (!document.querySelector('.custom-select').contains(e.target)) {
    options.style.display = 'none';
  }
});