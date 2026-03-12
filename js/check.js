document.addEventListener("DOMContentLoaded", function () {
    const checkBoxWrap = document.getElementById("checkBoxWrap");
    const allCheckbox = document.getElementById("agreeCheck");
    const eachCheckboxes = document.querySelectorAll(".chk-each");
  
    //   
    if (!allCheckbox || !checkBoxWrap) {
        console.log("Checkbox elements not found, skipping checkbox functionality");
        return;
    }

    //     →    
    allCheckbox.addEventListener("change", function () {
      eachCheckboxes.forEach((chk) => {
        chk.checked = allCheckbox.checked;
      });
  
      // border   
      checkBoxWrap.classList.toggle("active", allCheckbox.checked);
    });
  
    //     →    border  
    eachCheckboxes.forEach((chk) => {
      chk.addEventListener("change", function () {
        const allChecked = Array.from(eachCheckboxes).every((el) => el.checked);
        allCheckbox.checked = allChecked;
  
        //     border  
        checkBoxWrap.classList.toggle("active", allChecked);
      });
    });


    const checkboxWrap2 = document.getElementById("checkBoxWrap2");
    if (checkboxWrap2) {
        const checkbox2 = checkboxWrap2.querySelector("input[type='checkbox']");
        
        if (checkbox2) {
            checkbox2.addEventListener("change", function () {
                if (this.checked) {
                    checkboxWrap2.classList.add("active");
                } else {
                    checkboxWrap2.classList.remove("active");
                }
            });
        }
    }


  });