document.addEventListener("DOMContentLoaded", function () {
  const eyeButtons = document.querySelectorAll(".input-wrap1");

  eyeButtons.forEach(function (wrap) {
    const input = wrap.querySelector("input");
    const button = wrap.querySelector(".btn-eye");
    const icon = button.querySelector("img");

    button.addEventListener("click", function () {
      const isHidden = input.type === "password";
      input.type = isHidden ? "text" : "password";
      icon.src = isHidden ? "../images/ico_eye_on.svg" : "../images/ico_eye_off.svg";
      icon.alt = isHidden ? " " : " ";
    });
  });
});