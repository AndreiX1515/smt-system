document.getElementById("passportUpload").addEventListener("change", function () {
  const file = this.files[0];
  const preview = document.getElementById("passportPreview");

  if (file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      preview.src = e.target.result;
      preview.style.display = "block";
    };
    reader.readAsDataURL(file);
  } else {
    preview.style.display = "none";
  }
});
