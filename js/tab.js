document.addEventListener("DOMContentLoaded", function () {
  const tabs = document.querySelectorAll(".btn-tab2");

  tabs.forEach(tab => {
    tab.addEventListener("click", function (e) {
      e.preventDefault();

      // Remove active class from all tabs
      tabs.forEach(t => t.classList.remove("active"));

      // Add active class to clicked tab
      this.classList.add("active");

      // Get target from data-target attribute
      const target = this.getAttribute("data-target");

      // Handle tab-content-wrap switching (for visa-history)
      const tabContentWraps = document.querySelectorAll(".tab-content-wrap");
      if (tabContentWraps.length > 0 && target) {
        tabContentWraps.forEach(wrap => wrap.classList.remove("active"));
        const targetWrap = document.getElementById(target);
        if (targetWrap) {
          targetWrap.classList.add("active");
        }
      }
    });
  });


//   reservation-history
//   const tabs = document.querySelectorAll(".btn-tab2");
  const sections = {
    "": document.getElementById("intended"),
    "": document.getElementById("past"),
    "": document.getElementById("canceled"),
  };

  // null
  Object.values(sections).forEach(sec => {
    if (sec) sec.style.display = "none";
  });
  if (sections[""] && sections[""] !== null) {
    sections[""].style.display = "block";
  }

  tabs.forEach(tab => {
    tab.addEventListener("click", function (e) {
      e.preventDefault();

      // active
      tabs.forEach(t => t.classList.remove("active"));
      this.classList.add("active");

      //    (null  )
      Object.values(sections).forEach(sec => {
        if (sec) sec.style.display = "none";
      });

      //
      const tabText = this.textContent.trim().split(" ")[0]; // " 1" → ""
      if (sections[tabText] && sections[tabText]) {
        sections[tabText].style.display = "block";
      }
    });
  });

});

// btn-category   (null  )
const categoryButtons = document.querySelectorAll(".btn-category");
if (categoryButtons.length > 0) {
  categoryButtons.forEach(button => {
    button.addEventListener("click", function () {
      //   active 
      document.querySelectorAll(".btn-category").forEach(btn => btn.classList.remove("active"));
      //   active 
      this.classList.add("active");
    });
  });
}

document.addEventListener("DOMContentLoaded", function () {
  const tabs = document.querySelectorAll(".btn-alarmtab");
  const contents = document.querySelectorAll(".tab-content");

  if (tabs.length > 0 && contents.length > 0) {
    tabs.forEach(tab => {
      tab.addEventListener("click", function (e) {
        e.preventDefault();

        //   active 
        tabs.forEach(t => t.classList.remove("active"));
        //   active 
        this.classList.add("active");

        //   
        contents.forEach(c => c.style.display = "none");

        //   href   id   
        const targetId = this.getAttribute("href");
        if (targetId) {
          const targetElement = document.querySelector(targetId);
          if (targetElement) {
            targetElement.style.display = "block";
          }
        }
      });
    });
  }
});