// multi-editor.js
(() => {

  /* -----------------------------------------------------
     전역 저장소 (에디터별 상태 저장)
  ----------------------------------------------------- */
  const JW_EDITOR_REGISTERED = { done: false };
  const editorMap = new WeakMap();       // root(.jw-editor) → quill instance
  const htmlModeMap = new WeakMap();     // editorArea(.jweditor) → htmlMode(true/false)
  const originalHtmlMap = new WeakMap(); // editorArea → original html before HTML mode

  /* -----------------------------------------------------
     공통 유틸
  ----------------------------------------------------- */
  function getRootFromChild(el) {
    return el.closest(".jw-editor");
  }

  function getQuillFromChild(el) {
    const root = getRootFromChild(el);
    if (!root) return null;
    return editorMap.get(root);
  }

  function getEditorArea(root) {
    return root.querySelector(".jweditor");
  }


  /* -----------------------------------------------------
     Quill 한 번만 등록
  ----------------------------------------------------- */
  function registerQuillOnce() {
    if (JW_EDITOR_REGISTERED.done) return;
    if (typeof window.Quill === "undefined") return;

    // 허용 폰트 크기 등록
    const Size = Quill.import("attributors/style/size");
    Size.whitelist = ["12px", "14px", "16px", "18px", "24px"];
    Quill.register(Size, true);

    // 커스텀 이미지 Blot
    const BlockEmbed = Quill.import("blots/block/embed");
    class CustomImageBlot extends BlockEmbed {
      static create(value) {
        const node = super.create();
        node.setAttribute("src", value.src);
        node.setAttribute("data-index", value.index);
        return node;
      }
      static value(node) {
        return {
          src: node.getAttribute("src"),
          index: node.getAttribute("data-index")
        };
      }
    }
    CustomImageBlot.blotName = "jw-image";
    CustomImageBlot.tagName = "img";
    Quill.register(CustomImageBlot);

    JW_EDITOR_REGISTERED.done = true;
  }


  /* -----------------------------------------------------
     개별 에디터 초기화
  ----------------------------------------------------- */
  function initSingleEditor(root) {
    if (!root || editorMap.has(root)) return;

    const toolbar = root.querySelector(".toolbar");
    const editorArea = getEditorArea(root);
    if (!toolbar || !editorArea) return;

    const quill = new Quill(editorArea, {
      modules: {
        toolbar: {
          container: toolbar,
          handlers: {
            align: function (value) {
              this.quill.format("align", value || false);
            }
          }
        }
      }
    });

    editorMap.set(root, quill);
    htmlModeMap.set(editorArea, false);

    /* ----------- 포커스 있을 때만 UI 업데이트 ------------ */
    const fontSizeSelect = root.querySelector("select.ql-size");
    const fontColorBtn = root.querySelector(".font-color");
    const bgColorBtn = root.querySelector(".background-color");

    quill.on("selection-change", (range) => {
      if (!range) return;

      const format = quill.getFormat();

      if (fontSizeSelect) {
        fontSizeSelect.value = format.size || "";
      }

      if (fontColorBtn) {
        fontColorBtn.style.color =
          format.color || fontColorBtn.dataset.defaultColor || "#000";
      }

      if (bgColorBtn) {
        bgColorBtn.style.background =
          format.background || bgColorBtn.dataset.defaultBg || "#fff";
      }
    });

    /* 선택 전 기본 색상값 저장 */
    if (fontColorBtn && !fontColorBtn.dataset.defaultColor) {
      fontColorBtn.dataset.defaultColor = fontColorBtn.style.color || "#000";
    }
    if (bgColorBtn && !bgColorBtn.dataset.defaultBg) {
      bgColorBtn.dataset.defaultBg = bgColorBtn.style.background || "#fff";
    }
  }


  /* -----------------------------------------------------
     HTML 보기 토글
  ----------------------------------------------------- */
  function toggleHtmlView(btn) {
    const editorRoot = btn.closest(".jw-editor");
    const editorArea = getEditorArea(editorRoot);
    const quill = editorMap.get(editorRoot);

    const isHtml = htmlModeMap.get(editorArea);

    if (!isHtml) {
      // HTML 모드로 전환
      originalHtmlMap.set(editorArea, editorArea.innerHTML);

      editorArea.innerText = editorArea.innerHTML;
      editorArea.classList.add("html-mode");

      htmlModeMap.set(editorArea, true);
    } else {
      // 원래 모드로 복귀
      const origin = originalHtmlMap.get(editorArea);
      if (origin !== undefined) editorArea.innerHTML = origin;

      editorArea.classList.remove("html-mode");
      htmlModeMap.set(editorArea, false);

      quill.update();
    }
  }


  /* -----------------------------------------------------
     태그 제거
  ----------------------------------------------------- */
  function removeHtmlTags(btn) {
    const root = btn.closest(".jw-editor");
    const editorArea = getEditorArea(root);
    editorArea.innerHTML = editorArea.innerText;
  }


  /* -----------------------------------------------------
     폰트 사이즈 적용
  ----------------------------------------------------- */
  function fontsize(selectEl) {
    const quill = getQuillFromChild(selectEl);
    if (!quill) return;

    quill.format("size", selectEl.value || "");
  }


  /* -----------------------------------------------------
     색상 선택 로직 (최종 수정본)
  ----------------------------------------------------- */
  function setColor(button, type = 1) {
    const quill = getQuillFromChild(button);
    if (!quill) return;

    // 이미 열려있으면 다시 열리지 않게
    if (button.querySelector(".colorBox")) return;

    const colorBox = document.createElement("div");
    colorBox.classList.add("colorBox");

    const colors = getSpectrumColors(20);
    colors.push("#ffffff", "#000000");

    colors.forEach((color) => {
      const btnColor = document.createElement("button");
      btnColor.style.backgroundColor = color;

      btnColor.onclick = (event) => {
        if (type === 1) {
          // 글자색
          button.style.color = color; // 선택한 색상 유지
          quill.format("color", color);
        } else {
          // 배경색
          button.style.background = color;
          quill.format("background", color);
        }
        closeBox();
        event.stopPropagation();
      };

      colorBox.appendChild(btnColor);
    });

    button.appendChild(colorBox);

    function closeBox() {
      colorBox.remove();
      document.removeEventListener("click", closeOutside);
    }

    function closeOutside(e) {
      if (!colorBox.contains(e.target) && e.target !== button) {
        closeBox();
      }
    }

    document.addEventListener("click", closeOutside);
  }


  function getSpectrumColors(count) {
    const arr = [];
    for (let i = 0; i < count; i++) {
      const hue = (i / count) * 360;
      arr.push(hslToHex(hue, 100, 50));
    }
    return arr;
  }

  function hslToHex(h, s, l) {
    s /= 100;
    l /= 100;
    const c = (1 - Math.abs(2 * l - 1)) * s;
    const x = c * (1 - Math.abs((h / 60) % 2 - 1));
    const m = l - c / 2;
    let r, g, b;

    if (h < 60) { r = c; g = x; b = 0; }
    else if (h < 120) { r = x; g = c; b = 0; }
    else if (h < 180) { r = 0; g = c; b = x; }
    else if (h < 240) { r = 0; g = x; b = c; }
    else if (h < 300) { r = x; g = 0; b = c; }
    else { r = c; g = 0; b = x; }

    r = Math.round((r + m) * 255);
    g = Math.round((g + m) * 255);
    b = Math.round((b + m) * 255);

    return (
      "#" +
      r.toString(16).padStart(2, "0") +
      g.toString(16).padStart(2, "0") +
      b.toString(16).padStart(2, "0")
    );
  }


  /* -----------------------------------------------------
     이미지 삽입
  ----------------------------------------------------- */
  let imageIndex = 0;
  function insertImage(input) {
    const quill = getQuillFromChild(input);
    if (!quill) return;

    const file = input.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      const base64 = e.target.result;
      const range = quill.getSelection(true);
      const index = range ? range.index : quill.getLength();

      quill.insertEmbed(index, "jw-image", {
        src: base64,
        index: imageIndex++
      });
      quill.setSelection(index + 1);
    };
    reader.readAsDataURL(file);
  }


  /* -----------------------------------------------------
     페이지 전체 에디터 자동 적용
  ----------------------------------------------------- */
  function board() {
    if (typeof window.Quill === "undefined") return;
    registerQuillOnce();
    document.querySelectorAll(".jw-editor").forEach(initSingleEditor);
  }


  /* -----------------------------------------------------
     외부로 공개 함수
  ----------------------------------------------------- */
  window.board = board;
  window.toggleHtmlView = toggleHtmlView;
  window.removeHtmlTags = removeHtmlTags;
  window.fontsize = fontsize;
  window.setColor = setColor;
  window.insertImage = insertImage;

  document.addEventListener("DOMContentLoaded", board);

})();
