document.addEventListener("DOMContentLoaded", function () {
    //    (btn-mypage )
    // select-reservation.php, select-room.php, enter-customer-info.php, reservation-completed.php, reservation-detail.php, alarm.html  JS  
    if (!window.location.pathname.includes('select-reservation.php') && 
        !window.location.pathname.includes('select-room.php') &&
        !window.location.pathname.includes('enter-customer-info.php') &&
        !window.location.pathname.includes('reservation-completed.php') &&
        !window.location.pathname.includes('reservation-detail.php') &&
        !window.location.pathname.includes('alarm.html')) {
        document.querySelectorAll(".btn-mypage").forEach(function(btn) {
            // href  URL javascript:history.back()     
            const href = btn.getAttribute("href");
            if (href && href !== "#none" && href !== "javascript:void(0);" && !href.startsWith("#") && !href.includes("javascript:history.back()")) {
                //  URL     (    )
                return;
            }
            // btn-back    
            if (btn.classList.contains("btn-back")) {
                return;
            }
            
            // href #none    
            btn.addEventListener("click", function(e) {
                e.preventDefault();
                
                //   
                const img = this.querySelector("img");
                if (img) {
                    const imgSrc = img.getAttribute("src") || "";
                    //     
                    if (imgSrc.includes("ico_mypage.svg")) {
                        //      
                        const currentPath = window.location.pathname;
                        
                        // user   
                        if (currentPath.includes("/user/")) {
                            // /user/     mypage.html 
                            window.location.href = "mypage.html";
                        }
                        //   
                        else {
                            //  user/mypage.html 
                            window.location.href = "user/mypage.html";
                        }
                        return;
                    }
                }
                //   (  )  
                history.back();
            });
        });
    }

    const inputButtons = document.querySelectorAll(".btn-input");

    inputButtons.forEach(button => {
        //    active  
        updateText(button);

        //   active    
        button.addEventListener("click", function() {
            this.classList.toggle("active"); 
            updateText(this);
        });
    });

    function updateText(button) {
        if (button.classList.contains("active")) {
            button.textContent = " ";
        } else {
            button.textContent = " ";
        }
    }

    const titleButtons = document.querySelectorAll(".btn-title");
    const genderButtons = document.querySelectorAll(".btn-gender");
    const visaButtons = document.querySelectorAll(".btn-visa");
    const applyButtons = document.querySelectorAll(".btn-apply");
    const wifiButtons = document.querySelectorAll(".btn-wifi");
    const payButtons = document.querySelectorAll(".btn-pay");

    titleButtons.forEach(button => {
        button.addEventListener("click", function () {
            //  
            titleButtons.forEach(btn => btn.classList.remove("active"));
            //   
            this.classList.add("active");
        });
    });
    genderButtons.forEach(button => {
        button.addEventListener("click", function () {
            //  
            genderButtons.forEach(btn => btn.classList.remove("active"));
            //   
            this.classList.add("active");
        });
    });
    genderButtons.forEach(button => {
        button.addEventListener("click", function () {
            //  
            genderButtons.forEach(btn => btn.classList.remove("active"));
            //   
            this.classList.add("active");
        });
    });
    visaButtons.forEach(button => {
        button.addEventListener("click", function () {
            //  
            visaButtons.forEach(btn => btn.classList.remove("active"));
            //   
            this.classList.add("active");
        });
    });
    applyButtons.forEach(button => {
        button.addEventListener("click", function () {
            //  
            applyButtons.forEach(btn => btn.classList.remove("active"));
            //   
            this.classList.add("active");
        });
    });
    wifiButtons.forEach(button => {
        button.addEventListener("click", function () {
            //  
            wifiButtons.forEach(btn => btn.classList.remove("active"));
            //   
            this.classList.add("active");
        });
    });
    payButtons.forEach(button => {
        button.addEventListener("click", function () {
            //  
            payButtons.forEach(btn => btn.classList.remove("active"));
            //   
            this.classList.add("active");
        });
    });

    document.querySelectorAll(".btn_file_close").forEach(function(button) {
        button.addEventListener("click", function() {
            this.closest(".download-wrapper").style.display = "none";
        });
    });

    // schedyle.html
    function toggleSection(buttonSelector, targetId, showStyle = "block") {
        document.querySelectorAll(buttonSelector).forEach(button => {
            button.addEventListener("click", function () {
                const target = document.getElementById(targetId);
                if (!target) return;

                //  display  
                const currentDisplay = window.getComputedStyle(target).display;

                // 
                target.style.display = currentDisplay !== "none" ? "none" : showStyle;

                //   img active 
                const img = this.querySelector("img");
                if (img) img.classList.toggle("active");
            });
        });
    }

    // 
    toggleSection(".btn-fold1", "profile", "flex");
    toggleSection(".btn-fold2", "card", "block");
    toggleSection(".btn-fold3", "card2", "block");


});

// :   " "  (    )
document.addEventListener("DOMContentLoaded", function () {
  // SMT   -   href     
  if (window.location.pathname.includes('mypage.html') ||
      window.location.pathname.includes('reservation-history.php') ||
      window.location.pathname.includes('visa-history.html') ||
      window.location.pathname.includes('visa-detail-inadequate.html') ||
      window.location.pathname.includes('visa-detail-examination.html') ||
      window.location.pathname.includes('visa-detail-rebellion.html') ||
      window.location.pathname.includes('visa-detail-completion.php') ||
      window.location.pathname.includes('traveler-info-detail.html') ||
      window.location.pathname.includes('inquiry.php')) {
    return;
  }
  // SMT  
  const backAnchors = Array.from(document.querySelectorAll('a.btn-back, a.btn-mypage'));
  backAnchors.forEach((a) => {
    const img = a.querySelector('img');
    const imgSrc = (img && img.getAttribute('src')) ? img.getAttribute('src') : '';
    const isBackIcon = a.classList.contains('btn-back') || imgSrc.includes('ico_back');
    if (!isBackIcon) return;

    a.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
        history.back();
    }, { capture: true });
  });
});

// schedule.html   
document.addEventListener("DOMContentLoaded", () => {
  const btnOpen = document.querySelector(".btn-profile-view");
  const btnClose = document.querySelector(".btn-close-modal");
  const profileModal = document.querySelector(".profile-modal");
  const layer = document.querySelector(".layer");

  if (btnOpen && btnClose && profileModal && layer) {
    // 
    btnOpen.addEventListener("click", () => {
      profileModal.classList.add("active");
      layer.classList.add("active");
    });

    // 
    btnClose.addEventListener("click", () => {
      profileModal.classList.remove("active");
      layer.classList.remove("active");
    });

    //     ()
    layer.addEventListener("click", () => {
      profileModal.classList.remove("active");
      layer.classList.remove("active");
    });
  }
});

// footer 
document.querySelectorAll(".footer-fold-btn").forEach(button => {
  button.addEventListener("click", function () {
    //    active 
    const img = this.querySelector("img");
    if (img) img.classList.toggle("active");

    // footer_cont  
    const footerCont = document.getElementById("footer_cont");
    if (footerCont) {
      if (footerCont.style.display === "block" || footerCont.style.display === "") {
        footerCont.style.display = "none";   //  block → none
      } else {
        footerCont.style.display = "block";
      }
    }
  });
});


// btn-product      
const btnProduct = document.querySelector(".btn-product");
if (btnProduct) {
    btnProduct.addEventListener("click", function() {
        const imgDetails = document.querySelector(".img-details");
        const btnWrap = document.querySelector(".btn-wrap");
        const img = this.querySelector("img");
        
        if (imgDetails) imgDetails.classList.toggle("active");
        if (img) img.classList.toggle("active");
        if (btnWrap) btnWrap.classList.toggle("active");
    });
}

document.querySelectorAll(".btn-folding").forEach(function(btn) {
  btn.addEventListener("click", function(e) {
    e.preventDefault(); // a   
    const li = this.closest("li"); 
    const cardWrap = li.querySelector(".card-wrap");
    const img = this.querySelector("img");

    cardWrap.classList.toggle("active");
    img.classList.toggle("active");
  });
});

document.querySelectorAll(".btn-tab2").forEach(function(tab) {
  tab.addEventListener("click", function(e) {
    e.preventDefault(); //    
    const targetId = this.getAttribute("href"); // href   (#schedule )
    const targetElement = document.querySelector(targetId);

    if (targetElement) {
      targetElement.scrollIntoView({
        behavior: "smooth", //  
        block: "start"      //    
      });
    }

    //  active   (  )
    document.querySelectorAll(".btn-tab2").forEach(btn => btn.classList.remove("active"));
    this.classList.add("active");
  });
});

