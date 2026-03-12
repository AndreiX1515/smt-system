document.addEventListener("DOMContentLoaded", function () {
  const counters = document.querySelectorAll(".counter");

  counters.forEach(function (counter) {
    const minusBtn = counter.querySelector(".btn-minus");
    const plusBtn = counter.querySelector(".btn-plus");
    const countDisplay = counter.querySelector(".count-value");

    let count = parseInt(countDisplay.textContent);

    function updateState() {
      countDisplay.textContent = count;

      //  0   
      if (count === 0) {
        countDisplay.classList.add("is-zero");
        minusBtn.classList.add("disabled");
      } else {
        countDisplay.classList.remove("is-zero");
        minusBtn.classList.remove("disabled");
      }
    }

    plusBtn.addEventListener("click", function () {
      count++;
      updateState();
    });

    minusBtn.addEventListener("click", function () {
      if (count > 0) {
        count--;
        updateState();
      }
    });

    //   
    updateState();
  });
});
