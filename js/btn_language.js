<script>
document.addEventListener("DOMContentLoaded", function() {
    const langTxt = document.querySelector(".lang-txt");
    const btnEng = document.getElementById("btn_eng");
    const btnTag = document.getElementById("btn_tag");

    //   
    langTxt.textContent = "English";

    // English   
    btnEng.addEventListener("click", function() {
        langTxt.textContent = "English";
        btnEng.classList.add("active");
        btnTag.classList.remove("active");
    });

    // Tagalog   
    btnTag.addEventListener("click", function() {
        langTxt.textContent = "Tagalog";
        btnTag.classList.add("active");
        btnEng.classList.remove("active");
    });
});
</script>
