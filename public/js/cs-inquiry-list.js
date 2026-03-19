/**
 * CS Admin - Inquiry List Page
 * UI must match tools/inquiry-list.html
 */

let currentPage = 1;
const limit = 20;

const currentFilters = {
  replyStatus: "",
  processingStatus: "",
  sortOrder: "latest",
  search: "",
};

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = String(text ?? "");
  return div.innerHTML;
}

function mapInquiryTypeLabel(raw) {
  const v = String(raw ?? "").toLowerCase().trim();
  // 서버에서 inquiryType은 UI 값(product/reservation/payment/cancel/other)로 내려오도록 통일
  if (v === "product") return "Product Inquiry";
  if (v === "reservation") return "Reservation Inquiry";
  if (v === "payment") return "Payment Inquiry";
  if (v === "cancel" || v === "cancellation") return "Cancellation Inquiry";
  if (v === "other") return "Other";
  // 혹시 legacy(DB category)가 그대로 오는 경우도 방어
  if (v === "general") return "Product Inquiry";
  if (v === "booking") return "Reservation Inquiry";
  if (v === "complaint") return "Cancellation Inquiry";
  if (v === "suggestion") return "Other";
  return "Other";
}

function normalizeReplyStatusLabel(v) {
  const s = String(v ?? "");
  if (!s) return "Not Responded";
  const low = s.toLowerCase();
  if (low.includes("answered") || low.includes("complete")) return "Response Complete";
  if (low.includes("unanswered") || low.includes("not")) return "Not Responded";
  return s;
}

function normalizeProcessingStatusLabel(v) {
  const s = String(v ?? "");
  if (!s) return "Received";
  const low = s.toLowerCase();
  if (low.includes("received") || low.includes("open") || low.includes("pending")) return "Received";
  // NOTE: "Processing Complete"는 문자열에 processing/complete 둘 다 포함하므로
  // complete 계열을 먼저 판정해야 한다. (기존 버그: complete인데 In Progress로 표시됨)
  if (low.includes("complete") || low.includes("resolved") || low.includes("closed")) return "Processing Complete";
  if (low.includes("processing") || low.includes("progress") || low.includes("in_progress")) return "In Progress";
  return s;
}

async function ensureSessionOrRedirect() {
  try {
    const res = await fetch("../backend/api/check-session.php", {
      credentials: "same-origin",
      cache: "no-store",
    });
    const data = await res.json().catch(() => ({}));
    if (!data || !data.authenticated) {
      window.location.href = "../index.html";
      return false;
    }
    return true;
  } catch (e) {
    window.location.href = "../index.html";
    return false;
  }
}

function readFiltersFromUI() {
  currentFilters.replyStatus = document.getElementById("replyStatusFilter")?.value || "";
  currentFilters.processingStatus = document.getElementById("processingStatusFilter")?.value || "";
  currentFilters.sortOrder = document.getElementById("sortOrderFilter")?.value || "latest";
  currentFilters.search = document.getElementById("searchInput")?.value?.trim?.() || "";
}

async function loadInquiries(page = currentPage) {
  const tbody = document.getElementById("inquiriesTableBody");
  if (tbody) {
    tbody.innerHTML = `<tr><td colspan="6" class="is-center">Loading...</td></tr>`;
  }

  const params = new URLSearchParams({
    action: "getInquiries",
    page: String(page),
    limit: String(limit),
  });

  if (currentFilters.replyStatus) params.append("replyStatus", currentFilters.replyStatus);
  if (currentFilters.processingStatus) params.append("processingStatus", currentFilters.processingStatus);
  if (currentFilters.sortOrder) params.append("sortOrder", currentFilters.sortOrder);
  if (currentFilters.search) params.append("search", currentFilters.search);

  const res = await fetch(`../backend/api/cs-api.php?${params.toString()}`, {
    credentials: "same-origin",
  });

  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const result = await res.json();

  const totalCountEl = document.getElementById("totalCount");
  const pagination = result?.data?.pagination || {};
  if (totalCountEl) totalCountEl.textContent = String(pagination.totalCount ?? 0);

  const inquiries = Array.isArray(result?.data?.inquiries) ? result.data.inquiries : [];

  if (tbody) {
    if (inquiries.length === 0) {
      tbody.innerHTML = `<tr><td colspan="6" class="is-center">No inquiries.</td></tr>`;
    } else {
      tbody.innerHTML = inquiries
        .map((inquiry) => {
          const createdDate = inquiry.createdAt ? String(inquiry.createdAt).split(" ")[0] : "";
          return `
            <tr onclick="window.location.href='inquiry-detail.html?id=${encodeURIComponent(inquiry.inquiryId)}'" style="cursor:pointer;">
              <td class="is-center">${escapeHtml(inquiry.rowNum ?? "")}</td>
              <td class="is-center">${escapeHtml(mapInquiryTypeLabel(inquiry.inquiryType))}</td>
              <td>${escapeHtml(inquiry.inquiryTitle ?? "")}</td>
              <td class="is-center">${escapeHtml(createdDate)}</td>
              <td class="is-center">${escapeHtml(normalizeReplyStatusLabel(inquiry.replyStatus))}</td>
              <td class="is-center">${escapeHtml(normalizeProcessingStatusLabel(inquiry.processingStatus))}</td>
            </tr>
          `;
        })
        .join("");
    }
  }

  updatePagination({
    currentPage: Number(pagination.currentPage || page || 1),
    totalPages: Number(pagination.totalPages || 1),
  });

  currentPage = Number(pagination.currentPage || page || 1);
}

function updatePagination({ currentPage: cp, totalPages: tp }) {
  const current = Math.max(1, Number(cp || 1));
  const totalPages = Math.max(1, Number(tp || 1));

  const pageNumbersEl = document.getElementById("pageNumbers");
  if (pageNumbersEl) {
    pageNumbersEl.innerHTML = "";
    const maxVisiblePages = 5;
    let startPage = Math.max(1, current - Math.floor(maxVisiblePages / 2));
    let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
    if (endPage - startPage < maxVisiblePages - 1) {
      startPage = Math.max(1, endPage - maxVisiblePages + 1);
    }
    for (let i = startPage; i <= endPage; i++) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "p" + (i === current ? " show" : "");
      button.setAttribute("role", "listitem");
      if (i === current) button.setAttribute("aria-current", "page");
      button.textContent = String(i);
      button.onclick = () => loadInquiries(i);
      pageNumbersEl.appendChild(button);
    }
  }

  const firstPageBtn = document.getElementById("firstPage");
  if (firstPageBtn) {
    firstPageBtn.disabled = current === 1;
    firstPageBtn.setAttribute("aria-disabled", String(current === 1));
    firstPageBtn.onclick = current === 1 ? null : () => loadInquiries(1);
  }

  const prevPageBtn = document.getElementById("prevPage");
  if (prevPageBtn) {
    prevPageBtn.disabled = current === 1;
    prevPageBtn.setAttribute("aria-disabled", String(current === 1));
    prevPageBtn.onclick = current === 1 ? null : () => loadInquiries(current - 1);
  }

  const nextPageBtn = document.getElementById("nextPage");
  if (nextPageBtn) {
    nextPageBtn.disabled = current >= totalPages;
    nextPageBtn.setAttribute("aria-disabled", String(current >= totalPages));
    nextPageBtn.onclick = current >= totalPages ? null : () => loadInquiries(current + 1);
  }

  const lastPageBtn = document.getElementById("lastPage");
  if (lastPageBtn) {
    lastPageBtn.disabled = current >= totalPages;
    lastPageBtn.setAttribute("aria-disabled", String(current >= totalPages));
    lastPageBtn.onclick = current >= totalPages ? null : () => loadInquiries(totalPages);
  }
}

async function downloadInquiries() {
  const params = new URLSearchParams({ action: "downloadInquiries" });
  if (currentFilters.replyStatus) params.append("replyStatus", currentFilters.replyStatus);
  if (currentFilters.processingStatus) params.append("processingStatus", currentFilters.processingStatus);
  if (currentFilters.sortOrder) params.append("sortOrder", currentFilters.sortOrder);
  if (currentFilters.search) params.append("search", currentFilters.search);

  const response = await fetch(`../backend/api/cs-api.php?${params.toString()}`, {
    credentials: "same-origin",
  });
  if (!response.ok) throw new Error(`HTTP ${response.status}`);
  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `inquiries_${new Date().toISOString().split("T")[0]}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
}

document.addEventListener("DOMContentLoaded", async () => {
  const ok = await ensureSessionOrRedirect();
  if (!ok) return;

  const replySel = document.getElementById("replyStatusFilter");
  const procSel = document.getElementById("processingStatusFilter");
  const sortSel = document.getElementById("sortOrderFilter");
  const searchInput = document.getElementById("searchInput");
  const searchBtn = document.getElementById("searchBtn");
  const downloadBtn = document.getElementById("downloadBtn");

  const onFilterChange = () => {
    readFiltersFromUI();
    currentPage = 1;
    loadInquiries(1).catch((e) => {
      console.error("Error loading inquiries:", e);
      const tbody = document.getElementById("inquiriesTableBody");
      if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="is-center">Failed to load.</td></tr>`;
    });
  };

  replySel?.addEventListener("change", onFilterChange);
  procSel?.addEventListener("change", onFilterChange);
  sortSel?.addEventListener("change", onFilterChange);

  // Search (debounced)
  let __searchTimer = null;
  const triggerSearch = () => {
    if (__searchTimer) clearTimeout(__searchTimer);
    __searchTimer = setTimeout(() => onFilterChange(), 250);
  };
  searchInput?.addEventListener("input", triggerSearch);
  searchInput?.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
      e.preventDefault();
      onFilterChange();
    }
  });
  searchBtn?.addEventListener("click", (e) => {
    e.preventDefault();
    onFilterChange();
  });

  downloadBtn?.addEventListener("click", () => {
    readFiltersFromUI();
    downloadInquiries().catch((e) => {
      console.error("Error downloading inquiries:", e);
      alert("Download failed.");
    });
  });

  readFiltersFromUI();
  await loadInquiries(1);
});

