document.addEventListener("DOMContentLoaded", function () {
    //    
    const calendarDates = document.querySelectorAll(".calendar tbody td:not(.inactive):not(.close):not(.today)");
    
    calendarDates.forEach(date => {
        //       
        if (date.textContent.trim() && !date.classList.contains("inactive") && !date.classList.contains("close")) {
            date.addEventListener("click", function() {
                //   
                calendarDates.forEach(d => d.classList.remove("active"));
                
                //   
                this.classList.add("active");
                
                console.log("Selected date:", this.textContent.trim());
            });
        }
    });

    //    
    const prevBtn = document.querySelector(".ico_arrow_round_left");
    const nextBtn = document.querySelector(".ico_arrow_round_right");
    const monthDisplay = document.querySelector(".text.fz16.fw600.lh24.black12");
    
    let currentMonth = 4; // April = 4
    let currentYear = 2025;
    
    const monthNames = [
        "January", "February", "March", "April", "May", "June",
        "July", "August", "September", "October", "November", "December"
    ];
    
    if (prevBtn) {
        prevBtn.addEventListener("click", function() {
            currentMonth--;
            if (currentMonth < 1) {
                currentMonth = 12;
                currentYear--;
            }
            updateCalendarDisplay();
        });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener("click", function() {
            currentMonth++;
            if (currentMonth > 12) {
                currentMonth = 1;
                currentYear++;
            }
            updateCalendarDisplay();
        });
    }
    
    function updateCalendarDisplay() {
        if (monthDisplay) {
            monthDisplay.textContent = `${monthNames[currentMonth - 1]} ${currentYear}`;
        }
    }

    //    hover  
    const availableDates = document.querySelectorAll(".calendar tbody td:not(.inactive):not(.close):not(.today)");
    availableDates.forEach(date => {
        if (date.textContent.trim()) {
            date.style.cursor = "pointer";
            
            date.addEventListener("mouseenter", function() {
                if (!this.classList.contains("active")) {
                    this.style.backgroundColor = "#f0f0f0";
                }
            });
            
            date.addEventListener("mouseleave", function() {
                if (!this.classList.contains("active")) {
                    this.style.backgroundColor = "";
                }
            });
        }
    });
});