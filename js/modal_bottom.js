document.addEventListener("DOMContentLoaded", function() {
    const btnMore = document.querySelector(".btn-more");
    const btnClose = document.querySelector(".btn-close");
    const layer = document.querySelector(".layer");
    const modalBottom = document.querySelector(".modal-bottom");

    // .btn-more   active 
    btnMore.addEventListener("click", function() {
        layer.classList.add("active");
        modalBottom.classList.add("active");
    });

    // .btn-close   active 
    btnClose.addEventListener("click", function() {
        layer.classList.remove("active");
        modalBottom.classList.remove("active");
    });

    const langButtons = document.querySelectorAll(".btn-language");

    langButtons.forEach(function(button) {
        button.addEventListener("click", function() {
            //   active 
            langButtons.forEach(btn => btn.classList.remove("active"));
            //   active 
            this.classList.add("active");
        });
    });

});