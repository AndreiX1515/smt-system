document.addEventListener("DOMContentLoaded", function () {
    const inputButtons = document.querySelectorAll(".btn-input");

    inputButtons.forEach(button => {
        // 페이지 로딩 시 active 상태 확인
        updateText(button);

        // 클릭 시 active 토글 및 텍스트 변경
        this.classList.toggle("active"); 
        updateText(this);
    });

    function updateText(button) {
        if (button.classList.contains("active")) {
            button.textContent = "Input complete";
        } else {
            button.textContent = "Input before";
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
            // 모두 비활성화
            titleButtons.forEach(btn => btn.classList.remove("active"));
            // 현재 버튼만 활성화
            this.classList.add("active");
        });
    });
    genderButtons.forEach(button => {
        button.addEventListener("click", function () {
            // 모두 비활성화
            genderButtons.forEach(btn => btn.classList.remove("active"));
            // 현재 버튼만 활성화
            this.classList.add("active");
        });
    });
    genderButtons.forEach(button => {
        button.addEventListener("click", function () {
            // 모두 비활성화
            genderButtons.forEach(btn => btn.classList.remove("active"));
            // 현재 버튼만 활성화
            this.classList.add("active");
        });
    });
    visaButtons.forEach(button => {
        button.addEventListener("click", function () {
            // 모두 비활성화
            visaButtons.forEach(btn => btn.classList.remove("active"));
            // 현재 버튼만 활성화
            this.classList.add("active");
        });
    });
    applyButtons.forEach(button => {
        button.addEventListener("click", function () {
            // 모두 비활성화
            applyButtons.forEach(btn => btn.classList.remove("active"));
            // 현재 버튼만 활성화
            this.classList.add("active");
        });
    });
    wifiButtons.forEach(button => {
        button.addEventListener("click", function () {
            // 모두 비활성화
            wifiButtons.forEach(btn => btn.classList.remove("active"));
            // 현재 버튼만 활성화
            this.classList.add("active");
        });
    });
    payButtons.forEach(button => {
        button.addEventListener("click", function () {
            // 모두 비활성화
            payButtons.forEach(btn => btn.classList.remove("active"));
            // 현재 버튼만 활성화
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

                // 현재 display 상태 확인
                const currentDisplay = window.getComputedStyle(target).display;

                // 토글
                target.style.display = currentDisplay !== "none" ? "none" : showStyle;

                // 버튼 안 img active 토글
                const img = this.querySelector("img");
                if (img) img.classList.toggle("active");
            });
        });
    }

    // 적용
    toggleSection(".btn-fold1", "profile", "flex");
    toggleSection(".btn-fold2", "card", "block");
    toggleSection(".btn-fold3", "card2", "block");


});

// schedule.html 프로필 보기 모달
document.addEventListener("DOMContentLoaded", () => {
  const btnOpen = document.querySelector(".btn-profile-view");
  const btnClose = document.querySelector(".btn-close-modal");
  const profileModal = document.querySelector(".profile-modal");
  const layer = document.querySelector(".layer");

  if (btnOpen && btnClose && profileModal && layer) {
    // 열기
    btnOpen.addEventListener("click", () => {
      profileModal.classList.add("active");
      layer.classList.add("active");
    });

    // 닫기
    btnClose.addEventListener("click", () => {
      profileModal.classList.remove("active");
      layer.classList.remove("active");
    });

    // 레이어 클릭 시 닫기 (옵션)
    layer.addEventListener("click", () => {
      profileModal.classList.remove("active");
      layer.classList.remove("active");
    });
  }
});

// footer 버튼
document.querySelectorAll(".footer-fold-btn").forEach(button => {
  button.addEventListener("click", function () {
    // 버튼 안의 이미지 active 토글
    const img = this.querySelector("img");
    if (img) img.classList.toggle("active");

    // footer_cont 열고 닫기
    const footerCont = document.getElementById("footer_cont");
    if (footerCont) {
      if (footerCont.style.display === "block" || footerCont.style.display === "") {
        footerCont.style.display = "none";   // 처음엔 block → none
      } else {
        footerCont.style.display = "block";
      }
    }
  });
});


document.querySelector(".btn-product").addEventListener("click", function() {
    document.querySelector(".img-details").classList.toggle("active");
    this.querySelector("img").classList.toggle("active");
    document.querySelector(".btn-wrap").classList.toggle("active");

});

document.querySelectorAll(".btn-folding").forEach(function(btn) {
  btn.addEventListener("click", function(e) {
    e.preventDefault(); // a태그 기본 동작 막기
    const li = this.closest("li"); 
    const cardWrap = li.querySelector(".card-wrap");
    const img = this.querySelector("img");

    cardWrap.classList.toggle("active");
    img.classList.toggle("active");
  });
});

document.querySelectorAll(".btn-tab2").forEach(function(tab) {
  tab.addEventListener("click", function(e) {
    e.preventDefault(); // 기본 앵커 이동 막기
    const targetId = this.getAttribute("href"); // href 값 가져오기 (#schedule 등)
    const targetElement = document.querySelector(targetId);

    if (targetElement) {
      targetElement.scrollIntoView({
        behavior: "smooth", // 부드럽게 스크롤
        block: "start"      // 섹션의 상단 기준으로 정렬
      });
    }

    // 탭 active 클래스 처리 (선택된 탭 강조)
    document.querySelectorAll(".btn-tab2").forEach(btn => btn.classList.remove("active"));
    this.classList.add("active");
  });
});

