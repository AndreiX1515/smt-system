// Global Internationalization System for Smart Travel
// 전역 다국어 지원 시스템

// Anti-flash fallback (in case i18n-boot.js wasn't included)
try {
    const root = document.documentElement;
    if (!root.hasAttribute('data-i18n-pending') && !root.hasAttribute('data-i18n-ready')) {
        root.setAttribute('data-i18n-pending', '1');
    }
} catch (e) {
    // ignore
}

// 모든 페이지에서 사용할 수 있는 다국어 텍스트
const globalLanguageTexts = {
    ko: {
        // 네비게이션 및 공통 UI
        home: "홈",
        mypage: "마이페이지",
        reservation: "예약",
        schedule: "일정",
        profile: "프로필",
        settings: "설정",
        logout: "로그아웃",
        login: "로그인",
        signup: "회원가입",
        back: "뒤로",
        next: "다음",
        confirm: "확인",
        cancel: "취소",
        save: "저장",
        edit: "편집",
        delete: "삭제",
        book_now: "예약하기",

        // 권한 설정 페이지
        appPermissions: "앱 권한 설정",
        permissionsRequired: "원활한 서비스 이용을 위해 다음 권한이 필요합니다.",
        location: "위치 정보",
        locationDesc: "현재 위치 기반 맞춤 여행 상품 및 가이드 서비스 제공",
        camera: "카메라",
        cameraDesc: "프로필 사진 등록 및 여행 중 사진 촬영",
        notification: "알림",
        notificationDesc: "예약 확인, 일정 변경, 이벤트 소식 등 중요한 알림 수신",
        storage: "저장소",
        storageDesc: "여행 서류 및 사진 저장, 오프라인 지도 다운로드",
        required: "필수",
        optional: "선택",
        permissionsNote: "권한은 언제든지 설정에서 변경할 수 있습니다.",

        // 홈 페이지
        todaysTrip: "Today's Trip",
        upcomingTrip: "Upcoming Trip",
        bySeason: "By Season",
        byRegion: "By Region",
        byTheme: "By Theme",
        seeAll: "See all",
        aboutUs: "About us",
        companyIntro: "회사 소개",
        companyIntroTitle: "회사 소개",
        companyIntroDescription: "스마트트래블은 필리핀 고객을 대상으로 하는 패키지 여행 서비스 기업입니다. 본 사업에서는 필리핀 관광객 관리, 유치 목적의 스마트 시스템에 기능 고도화 및 디자인 리뉴얼로 사용자가 더 편하게 사용 가능한 시스템 구축 진행할 예정입니다.",
        brandPhilosophy: "브랜드와 철학",
        partnership: "제휴 안내",
        partnershipInformation: "제휴안내",
        partnershipMainTitle: "Main Title",
        partnershipSubTitle: "Sub Title",
        partners: "협력사",
        partnershipInfo: "파트너십",

        // 여행 관련
        travelDay: "여행 1일차",
        meetingLocation: "Meeting Location",
        meetingTime: "Meeting Time",
        scheduleDetail: "일정 상세",
        guideLocation: "가이드 위치",
        reservationDetail: "예약 상세 내역",
        fullTravelSchedule: "전체 여행 일정",
        history: "히스토리",
        noLocationHistory: "위치 히스토리가 없습니다.",

        // 푸터
        privacyPolicy: "개인정보처리방침",
        termsOfUse: "이용약관",
        customerService: "고객센터",
        domestic: "국내",
        overseas: "해외",
        businessHours: "(평일 09~18시)",
        representative: "대표자",
        address: "주소",
        businessRegistrationNumber: "사업자 등록 번호",
        telemarketingRegistrationNumber: "통신판매업 신고 번호",
        email: "이메일",
        fax: "FAX",
        privacyOfficer: "개인정보보호책임자",
        tourismBusinessRegistrationNumber: "관광사업자등록번호",
        registrationOffice: "등록관청",
        copyright: "COPYRIGHT ⓒ SMART TRAVEL ALL RIGHTS RESERVED.",
        // 권한 설정 모달
        serviceRequiresPermission: "This service requires permission to proceed",
        locationOptional: "위치 (선택)",
        locationDesc: "주변 정보 및 서비스 제공에 활용",
        notificationOptional: "알림 (선택)",
        notificationDesc: "푸시 알림 안내",

        // 마이페이지
        loginPrompt: "로그인하고<br>다음 여행을 계획하세요",
        welcomeMessage: "환영합니다, {userName}님!",
        loginSignup: "로그인/회원가입",
        accountSetting: "계정 설정",
        packageTours: "패키지 투어",
        bySeason: "계절별",
        byRegion: "지역별",
        byTheme: "테마별",
        private: "프라이빗",
        dayTrip: "당일치기",
        recentActivity: "최근 활동",
        manageMyTrips: "내 여행 관리",
        reservationHistory: "예약 내역",
        upcoming: "예정된",
        past: "지난",
        cancelled: "취소된",
        haveQuestions: "문의사항이 있으신가요?",
        customerSupport: "고객지원",
        visaApplicationHistory: "비자 신청 내역",
        // 예약 내역 관련 추가 텍스트
        noReservationHistory: "예약 내역이 없습니다.",
        noUpcomingTrips: "아직 예정된 여행이 없습니다",
        noCompletedTrips: "완료된 여행이 없습니다",
        noCancelledBookings: "취소된 예약이 없습니다",
        planNewTrip: "새로운 여행을 계획해보세요",
        browseProducts: "상품 둘러보기",
        loadingReservationHistory: "예약 내역을 불러오는 중...",
        loadBookingHistoryFailed: "예약 내역을 불러오는데 실패했습니다.",
        
        // 예약 상세 페이지
        reservation_detail: "예약 상세",
        product_info: "상품 정보",
        trip_dates: "여행 일정",
        reservation_info: "예약 정보",
        reservation_number: "예약번호",
        reservation_status: "예약 상태",
        guests: "여행객",
        room_option: "객실 옵션",
        extra_baggage: "기내 수화물 추가",
        breakfast_request: "조식 신청",
        wifi_rental: "와이파이 대여",
        seat_preference: "항공 좌석 요청사항",
        other_requests: "기타 요청사항",
        total_price: "총 상품 금액",
        booker_info: "예약자 정보",
        traveler_info: "여행자 정보",
        payment_info: "결제 정보",
        payment_method: "결제 방법",
        payment_datetime: "결제 일시",
        order_amount: "주문 금액",
        total_amount_paid: "총 결제 금액",
        payment_receipt: "결제 영수증",
        trip_schedule: "여행 일정",
        view_schedule: "일정 보러가기",
        download_full_schedule: "전체 일정 다운로드",
        usage_guide: "이용안내",
        usage_guide_text: "이용안내 문구가 출력되는 곳입니다.",
        download_guide: "안내문 다운로드",
        guide_info: "가이드 정보",
        contact: "연락처",
        about_me: "소개",
        check_guide_location: "가이드 위치 확인",
        cancellation_policy: "취소 규정",
        cancellation_policy_text: "• 출발 15일 전: 여행요금 100% 환불(취소수수료 없음)\n• 출발 8-14일 전: 여행요금 50% 환불(취소수수료 50%)\n• 출발 4-7일 전: 여행요금 30% 환불(취소수수료 70%)\n• 출발 1-3일 전: 여행요금 0% 환불(취소수수료 100%)",
        cancel_reservation: "예약 취소",
        have_questions: "문의사항이 있으신가요?",
        customer_support: "고객지원",
        loading_trip_dates: "여행 일정을 불러오는 중...",
        loading_booker_info: "예약자 정보를 불러오는 중...",
        loading_email: "이메일을 불러오는 중...",
        loading_phone: "전화번호를 불러오는 중...",
        loading_payment_method: "결제 방법을 불러오는 중...",
        loading_payment_datetime: "결제 일시를 불러오는 중...",
        loading_guide_info: "가이드 정보를 불러오는 중...",
        loading_guide_contact: "가이드 연락처를 불러오는 중...",
        loading_guide_intro: "가이드 소개를 불러오는 중...",
        not_selected: "선택안함",
        apply: "신청",
        no_request: "요청사항 없음",
        standard_room: "스탠다드룸",
        main_traveler: "대표 여행자",
        child_age: "아동(만 3~7세)",
        infant_age: "유아(만 0~2세)",
        settingsSupport: "설정 및 지원",
        notice: "공지사항",
        customerCenter: "고객센터",
        
        // 스케줄 페이지
        travel_schedule: "여행 일정",
        guide_location: "가이드 위치",
        itinerary_details: "일정 상세",
        timeline_view: "타임라인 보기",
        certified_guide: "인증 가이드",
        departure_notice: "정시에 출발하니 늦지 않게 도착해주세요. 광장의 왼쪽 골목으로 찾아오세요. 노란 깃발 버스를 찾아주세요.",
        registered: "등록됨",
        no_schedule_today: "해당 날짜에 예정된 일정이 없습니다.",
        schedule_loading: "일정을 불러오는 중...",
        schedule_error: "일정을 불러오는데 실패했습니다.",

        // 문의 페이지
        inquiry: "문의하기",
        inquiryHistory: "문의 내역",
        callSupport: "전화 지원",
        weekdaysHours: "평일 9시 - 18시",
        totalInquiries: "총 4건",
        productInquiry: "상품 문의",
        pendingReply: "답변 대기",
        replied: "답변 완료",
        inquiryTitle: "문의 제목",

        // 문의 페이지 추가 텍스트
        loadInquiriesFailed: "문의사항을 불러오는데 실패했습니다.",
        loadingInquiries: "문의사항을 불러오는 중...",
        newInquiry: "새 문의하기",
        noInquiries: "문의사항이 없습니다.",
        noPendingInquiries: "대기중인 문의가 없습니다.",
        noInProgressInquiries: "처리중인 문의가 없습니다.",
        noResolvedInquiries: "해결된 문의가 없습니다.",
        noClosedInquiries: "종료된 문의가 없습니다.",
        inquiriesUnit: "건",

        // 문의 작성 페이지
        oneOnOneInquiry: "1:1",
        replyEmailAddress: "답변 받을 이메일 주소",
        replyPhoneNumber: "답변 받을 휴대폰 번호",
        inquiryType: "문의 유형",
        selectInquiryType: "문의 유형을 선택해주세요",
        option1: "Option 1",
        option2: "Option 2",
        option3: "Option 3",
        inquiryContent: "문의 내용",
        enterContent: "내용을 입력해주세요",
        fileAttachment: "첨부파일",
        upload: "업로드",
        fileUploadLimit: "* 사진 및 파일은 최대 5개까지 등록 가능합니다",
        fileFormatLimit: "* JPG, JPEG, PNG, GIF, PDF 형식의 파일만 등록 가능하며, 각 파일은 10MB 미만이어야 합니다.",
        register: "등록",
        enterValidInfo: "올바른 정보를 입력해주세요.",
        enterInquiryContent: "문의 내용을 입력해주세요.",
        submitting: "제출 중...",
        inquirySubmitted: "문의가 성공적으로 제출되었습니다.",
        inquirySubmitFailed: "문의 제출에 실패했습니다.",
        networkError: "네트워크 오류가 발생했습니다. 다시 시도해주세요.",
        inquirySuccessTitle: "문의가 접수되었습니다",
        inquirySuccessDesc: "담당자가 확인 후 답변드릴 예정입니다.",

        // 비자 내역 페이지
        visaApplicationHistory: "비자 신청 내역",
        inadequateDocuments: "서류 미비",
        duringExamination: "심사중",
        completionIssuance: "발급 완료",
        rebellion: "반려",
        loadVisaHistoryFailed: "비자 신청 내역을 불러오는데 실패했습니다.",
        loadingVisaHistory: "비자 신청 내역을 불러오는 중...",
        newVisaApplication: "새 비자 신청",
        noVisaHistory: "비자 신청 내역이 없습니다.",
        
        // 알림 페이지
        noNotifications: "알림이 없습니다.",
        view_details: "자세히 보기",
        loading: "알림을 불러오는 중...",
        markAllAsRead: "모두 읽음",
        markAllAsReadConfirm: "모든 알림을 읽음 처리하시겠습니까?",
        allNotificationsMarkedRead: "모든 알림이 읽음 처리되었습니다.",
        markAsReadFailed: "읽음 처리에 실패했습니다.",
        markAsReadError: "읽음 처리 중 오류가 발생했습니다.",
        noInadequateVisas: "서류 미비인 비자가 없습니다.",
        noUnderReviewVisas: "심사중인 비자가 없습니다.",
        noApprovedVisas: "발급 완료된 비자가 없습니다.",
        noRejectedVisas: "반려된 비자가 없습니다.",
        
        // 비자 발급 완료 페이지
        visaApplicationNotFound: "비자 신청 정보를 찾을 수 없습니다.",
        visaNotYetIssued: "아직 비자 발급이 완료되지 않았습니다.",
        failedToLoadVisaApplication: "비자 신청 정보를 불러올 수 없습니다.",
        errorLoadingVisaApplication: "비자 신청 정보 로드 중 오류가 발생했습니다.",
        errorInitializingVisaPage: "페이지 로드 중 오류가 발생했습니다.",
        visaInformation: "비자 정보",
        visaType: "비자 유형",
        validPeriod: "유효 기간",
        applicationNumber: "신청 번호",
        processing: "처리 중...",
        downloadFailed: "다운로드 실패",
        visaFileNotFound: "비자 파일을 찾을 수 없습니다.",
        errorDownloadingVisa: "비자 다운로드 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.",
        errorDisplayingVisaInfo: "비자 정보 표시 중 오류가 발생했습니다.",

        // 공지사항 페이지
        notice: "공지사항",
        noNotices: "공지사항이 없습니다.",
        loadingNotices: "공지사항을 불러오는 중...",
        loadNoticesFailed: "공지사항을 불러오는데 실패했습니다.",
        general: "일반",
        booking: "예약",
        payment: "결제",
        visa: "비자",
        system: "시스템",
        author: "작성자",
        viewCount: "조회수",
        priorityHigh: "긴급",
        priorityMedium: "일반",
        priorityLow: "낮음",

        // 로그인 페이지
        logIn: "로그인",
        email: "이메일",
        password: "비밀번호",
        passwordPlaceholder: "8~12자, 영문/숫자/특수문자 포함",
        autoLogin: "자동 로그인",
        findId: "아이디 찾기",
        findPassword: "비밀번호 찾기",
        changePassword: "비밀번호 변경",
        signUp: "회원가입",
        noMemberInfo: "입력하신 정보와 일치하는 회원정보가 없습니다.",
        searching: "찾는 중...",
        
        // 설정 페이지
        logoutConfirm: "로그아웃하시겠어요?",
        logoutDescription: "로그아웃 시 홈화면으로 이동합니다",
        deleteConfirm: "정말 탈퇴하시겠어요?",
        deleteDescription: "탈퇴 시 모든 정보가 삭제되며 복구할 수 없습니다",
        deleteAccount: "회원 탈퇴",
        cannotDelete: "탈퇴할 수 없습니다",
        deleteRestrictDescription: "현재 예약된 여행이 있어 탈퇴가 제한됩니다",
        deleteComplete: "회원 탈퇴가 완료되었습니다",
        deleteAccountError: "회원 탈퇴 중 오류가 발생했습니다",

        // 회원가입 페이지
        name: "이름",
        phone: "연락처",
        phonePlaceholder: "'-' 없이 숫자만 입력",
        passwordConfirm: "비밀번호 확인",
        passwordConfirmPlaceholder: "비밀번호 확인",
        duplicateCheck: "중복 확인",
        // 회원가입 완료 팝업
        joinSuccessTitle: "가입이 완료되었습니다.",
        joinSuccessDesc: "로그인 해주세요",
        agreeAll: "전체 동의",
        privacyCollection: "개인정보 수집 및 이용 (필수)",
        privacyThirdParty: "개인정보 제3자 제공 (필수)",
        marketingConsent: "마케팅 이용 동의 (선택)",
        join: "회원가입",
        invalidEmailFormat: "이메일 형식이 올바르지 않습니다.",
        invalidPhoneFormat: "연락처 형식이 올바르지 않습니다.",
        invalidPasswordFormat: "비밀번호 형식이 올바르지 않습니다.",
        passwordRule: "8~12자, 영문/숫자/특수문자 포함",
        passwordMismatch: "비밀번호가 일치하지 않습니다.",

        // 계정 설정 페이지
        accountSettings: "계정 설정",
        editMemberInfo: "회원 정보 수정",
        changePassword: "비밀번호 변경",

        // 프로필 수정 페이지
        editProfile: "프로필 수정",
        updateProfile: "프로필 업데이트",
        profileUpdated: "프로필이 성공적으로 업데이트되었습니다.",
        profileUpdateFailed: "프로필 업데이트에 실패했습니다.",
        profileLoadFailed: "프로필 정보를 불러올 수 없습니다.",
        profileLoadError: "프로필 로드 중 오류가 발생했습니다.",
        profileUpdateError: "프로필 업데이트 중 오류가 발생했습니다.",
        loginRequired: "로그인이 필요합니다.",
        enterAllRequiredFields: "필수 정보를 모두 입력해주세요.",
        saving: "저장 중...",

        // 비밀번호 변경 페이지
        currentPassword: "기존 비밀번호",
        newPassword: "새 비밀번호",
        confirmNewPassword: "새 비밀번호 확인",
        passwordChanged: "비밀번호가 성공적으로 변경되었습니다.",
        passwordChangeFailed: "비밀번호 변경에 실패했습니다.",
        incorrectCurrentPassword: "기존 비밀번호가 올바르지 않습니다.",
        passwordChangeError: "비밀번호 변경 중 오류가 발생했습니다.",

        // 언어 선택
        selectLanguage: "사용하실 언어를 선택해주세요.",
        continue: "계속",
        english: "English",
        tagalog: "Tagalog",

        // product-info 페이지
        packageProducts: "패키지 상품",
        loadingPackages: "패키지를 불러오는 중...",
        loadMore: "더 보기",
        noPackagesInCategory: "해당 카테고리에 패키지가 없습니다",
        selectOtherCategory: "다른 카테고리를 선택해주세요",
        confirmed: "출발 확정",
        expand_intro: "상품 소개 펼치기",
        collapse_intro: "상품 소개 접기"
    },

    en: {
        // Navigation and Common UI
        home: "Home",
        mypage: "My Page",
        reservation: "Reservation",
        schedule: "Schedule",
        profile: "Profile",
        settings: "Settings",
        logout: "Logout",
        login: "Login",
        signup: "Sign Up",
        back: "Back",
        next: "Next",
        confirm: "Confirm",
        cancel: "Cancel",
        save: "Save",
        edit: "Edit",
        delete: "Delete",
        book_now: "Book Now",

        // Permissions Page
        appPermissions: "App Permissions",
        permissionsRequired: "The following permissions are required for optimal service.",
        location: "Location",
        locationDesc: "Provide customized travel products and guide services based on current location",
        camera: "Camera",
        cameraDesc: "Register profile photos and take photos during travel",
        notification: "Notifications",
        notificationDesc: "Receive important notifications for booking confirmations, schedule changes, and event updates",
        storage: "Storage",
        storageDesc: "Save travel documents and photos, download offline maps",
        required: "Required",
        optional: "Optional",
        permissionsNote: "Permissions can be changed in settings at any time.",

        // Home Page
        todaysTrip: "Today's Trip",
        upcomingTrip: "Upcoming Trip",
        bySeason: "By Season",
        byRegion: "By Region",
        byTheme: "By Theme",
        seeAll: "See all",
        aboutUs: "About us",
        companyIntro: "Company Info",
        companyIntroTitle: "Company Introduction",
        companyIntroDescription: "Smart Travel is a package travel service company targeting Filipino customers. In this business, we plan to build a smart system for Filipino tourist management and attraction purposes, with enhanced functionality and design renewal, so that users can use it more comfortably.",
        brandPhilosophy: "Brand & Philosophy",
        partnership: "Partnership",
        partnershipInformation: "Partnership Information",
        partnershipMainTitle: "Main Title",
        partnershipSubTitle: "Sub Title",
        partners: "Partners",
        partnershipInfo: "Partnership",

        // Travel Related
        travelDay: "Travel Day 1",
        meetingLocation: "Meeting Location",
        meetingTime: "Meeting Time",
        scheduleDetail: "Schedule Details",
        guideLocation: "Guide Location",
        reservationDetail: "Reservation Details",
        fullTravelSchedule: "Full Travel Schedule",
        history: "History",
        noLocationHistory: "No location history available.",

        // Footer
        privacyPolicy: "Privacy Policy",
        termsOfUse: "Terms of Use",
        customerService: "Customer Service",
        domestic: "Domestic",
        overseas: "Overseas",
        businessHours: "(Mon-Fri 09-18)",
        representative: "Representative",
        address: "Address",
        businessRegistrationNumber: "Business Registration Number",
        telemarketingRegistrationNumber: "Telemarketing Registration Number",
        email: "Email",
        fax: "FAX",
        privacyOfficer: "Privacy Officer",
        tourismBusinessRegistrationNumber: "Tourism Business Registration Number",
        registrationOffice: "Registration Office",
        copyright: "COPYRIGHT ⓒ SMART TRAVEL ALL RIGHTS RESERVED.",
        // Permission Modal
        serviceRequiresPermission: "This service requires permission to proceed",
        locationOptional: "Location (Optional)",
        locationDesc: "Used for providing nearby information and services",
        notificationOptional: "Notification (Optional)",
        notificationDesc: "Push notification alerts",

        // My Page
        loginPrompt: "Log in and<br>plan your next trip",
        welcomeMessage: "Welcome, {userName}!",
        loginSignup: "Login/Sign Up",
        accountSetting: "Account Settings",
        
        // Settings page
        settings: "Settings",
        language: "Language",
        thirdPartySharing: "Third-Party Information Sharing",
        marketingPreferences: "Marketing Preferences",
        logoutConfirm: "Would you like to log out?",
        logoutDescription: "Upon logging out, you will be moved to the home screen.",
        deleteConfirm: "Are you sure you want to withdraw?",
        deleteDescription: "All information will be deleted and cannot be recovered upon withdrawal.",
        deleteAccount: "Delete Account",
        cannotDelete: "Cannot withdraw",
        deleteRestrictDescription: "Withdrawal is restricted due to currently booked travel.",
        deleteComplete: "Membership withdrawal has been completed.",
        deleteAccountError: "An error occurred during membership withdrawal.",
        
        packageTours: "Package Tours",
        bySeason: "By Season",
        byRegion: "By Region",
        byTheme: "By Theme",
        private: "Private",
        dayTrip: "Day Trip",
        recentActivity: "Recent Activity",
        manageMyTrips: "Manage My Trips",
        reservationHistory: "Reservation History",
        upcoming: "Upcoming",
        past: "Past",
        cancelled: "Cancelled",
        haveQuestions: "Have any questions?",
        customerSupport: "Customer Support",
        visaApplicationHistory: "Visa Application History",
        // 예약 내역 관련 추가 텍스트
        noReservationHistory: "No reservation history.",
        noUpcomingTrips: "No upcoming trips yet",
        noCompletedTrips: "No completed trips",
        noCancelledBookings: "No cancelled bookings",
        planNewTrip: "Plan a new trip",
        browseProducts: "Browse Products",
        loadingReservationHistory: "Loading reservation history...",
        loadBookingHistoryFailed: "Failed to load reservation history.",
        settingsSupport: "Settings & Support",
        notice: "Notice",
        customerCenter: "Customer Center",

        // 문의 페이지
        inquiry: "Inquiry",
        inquiryHistory: "Inquiry History",
        callSupport: "Call Support",
        weekdaysHours: "Weekdays 9 AM - 6 PM",
        totalInquiries: "Total 4",
        productInquiry: "Product Inquiry",
        pendingReply: "Pending Reply",
        replied: "Replied",
        inquiryTitle: "Inquiry Title",

        // 문의 페이지 추가 텍스트
        loadInquiriesFailed: "Failed to load inquiries.",
        loadingInquiries: "Loading inquiries...",
        newInquiry: "New Inquiry",
        noInquiries: "No inquiries.",
        noPendingInquiries: "No pending inquiries.",
        noInProgressInquiries: "No inquiries in progress.",
        noResolvedInquiries: "No resolved inquiries.",
        noClosedInquiries: "No closed inquiries.",
        inquiriesUnit: "",

        // 문의 작성 페이지
        oneOnOneInquiry: "1:1 Inquiry",
        replyEmailAddress: "Reply Email Address",
        replyPhoneNumber: "Reply Phone Number",
        inquiryType: "Inquiry Type",
        selectInquiryType: "Please select inquiry type",
        option1: "Option 1",
        option2: "Option 2",
        option3: "Option 3",
        inquiryContent: "Inquiry Content",
        enterContent: "Please enter content",
        fileAttachment: "File Attachment",
        upload: "Upload",
        fileUploadLimit: "* Up to 5 photos and files can be uploaded",
        fileFormatLimit: "* Only JPG, JPEG, PNG, GIF, PDF format files are allowed, and each file must be less than 10MB.",
        register: "Register",
        enterValidInfo: "Please enter valid information.",
        enterInquiryContent: "Please enter inquiry content.",
        submitting: "Submitting...",
        inquirySubmitted: "Inquiry has been successfully submitted.",
        inquirySubmitFailed: "Failed to submit inquiry.",
        networkError: "Network error occurred. Please try again.",
        inquirySuccessTitle: "Your inquiry has been received",
        inquirySuccessDesc: "A staff member will check and respond to you.",

        // 비자 내역 페이지
        visaApplicationHistory: "Visa Application History",
        inadequateDocuments: "Inadequate Documents",
        duringExamination: "Under Review",
        completionIssuance: "Issued",
        rebellion: "Rejected",
        loadVisaHistoryFailed: "Failed to load visa application history.",
        loadingVisaHistory: "Loading visa application history...",
        newVisaApplication: "New Visa Application",
        noVisaHistory: "No visa application history.",
        
        // 알림 페이지
        noNotifications: "No notifications.",
        view_details: "View Details",
        loading: "Loading notifications...",
        markAllAsRead: "Mark All as Read",
        markAllAsReadConfirm: "Do you want to mark all notifications as read?",
        allNotificationsMarkedRead: "All notifications have been marked as read.",
        markAsReadFailed: "Failed to mark as read.",
        markAsReadError: "An error occurred while marking as read.",
        noInadequateVisas: "No visas with inadequate documents.",
        noUnderReviewVisas: "No visas under review.",
        noApprovedVisas: "No approved visas.",
        noRejectedVisas: "No rejected visas.",
        
        // 비자 발급 완료 페이지
        visaApplicationNotFound: "Visa application information not found.",
        visaNotYetIssued: "Visa issuance has not been completed yet.",
        failedToLoadVisaApplication: "Failed to load visa application information.",
        errorLoadingVisaApplication: "An error occurred while loading visa application information.",
        errorInitializingVisaPage: "An error occurred while loading the page.",
        visaInformation: "Visa Information",
        visaType: "Visa Type",
        validPeriod: "Valid Period",
        applicationNumber: "Application Number",
        processing: "Processing...",
        downloadFailed: "Download Failed",
        visaFileNotFound: "Visa file not found.",
        errorDownloadingVisa: "An error occurred while downloading the visa. Please try again later.",
        errorDisplayingVisaInfo: "An error occurred while displaying visa information.",

        // 공지사항 페이지
        notice: "Notice",
        noNotices: "No notices.",
        loadingNotices: "Loading notices...",
        loadNoticesFailed: "Failed to load notices.",
        general: "General",
        booking: "Booking",
        payment: "Payment",
        visa: "Visa",
        system: "System",
        author: "Author",
        viewCount: "Views",
        priorityHigh: "High",
        priorityMedium: "Medium",
        priorityLow: "Low",

        // Login Page
        logIn: "Log In",
        email: "Email",
        password: "Password",
        passwordPlaceholder: "8-12 characters, including letters/numbers/special characters",
        autoLogin: "Auto Login",
        findId: "Find ID",
        findPassword: "Find Password",
        changePassword: "Change Password",
        signUp: "Sign Up",
        noMemberInfo: "There is no member information that matches the information you entered.",
        searching: "Searching...",

        // Join Page
        name: "Name",
        phone: "Phone",
        phonePlaceholder: "Enter numbers only without '-'",
        passwordConfirm: "Confirm Password",
        passwordConfirmPlaceholder: "Confirm Password",
        duplicateCheck: "Check Duplicate",
        // Sign Up Complete popup
        joinSuccessTitle: "Registration has been completed.",
        joinSuccessDesc: "Please log in",
        agreeAll: "Agree All",
        privacyCollection: "Personal Information Collection and Use (Required)",
        privacyThirdParty: "Personal Information Third Party Provision (Required)",
        marketingConsent: "Marketing Use Consent (Optional)",
        join: "Sign Up",
        invalidEmailFormat: "Email format is incorrect.",
        invalidPhoneFormat: "Phone format is incorrect.",
        invalidPasswordFormat: "Password format is incorrect.",
        passwordRule: "8-12 characters, including letters, numbers, and special characters",
        passwordMismatch: "Passwords do not match.",

        // Account Settings Page
        accountSettings: "Account Settings",
        editMemberInfo: "Edit Member Information",
        changePassword: "Change Password",

        // Profile Edit Page
        editProfile: "Edit Member Information",
        updateProfile: "Update Profile",
        profileUpdated: "Profile has been successfully updated.",
        profileUpdateFailed: "Failed to update profile.",
        profileLoadFailed: "Unable to load profile information.",
        profileLoadError: "Error occurred while loading profile.",
        profileUpdateError: "Error occurred while updating profile.",
        loginRequired: "Login is required.",
        enterAllRequiredFields: "Please enter all required information.",
        saving: "Saving...",

        // Password Change Page
        currentPassword: "Current Password",
        newPassword: "New Password",
        confirmNewPassword: "Confirm New Password",
        passwordChanged: "Password has been successfully changed.",
        passwordChangeFailed: "Failed to change password.",
        incorrectCurrentPassword: "Current password is incorrect.",
        passwordChangeError: "Error occurred while changing password.",

        // Schedule Page
        travel_schedule: "Travel Schedule",
        guide_location: "Guide Location",
        itinerary_details: "Itinerary Details",
        timeline_view: "Timeline View",
        certified_guide: "Certified Guide",
        departure_notice: "Please arrive on time for departure. Find us in the left alley of the square. Look for the yellow flag bus.",
        registered: "Registered",
        no_schedule_today: "No schedule planned for this date.",
        schedule_loading: "Loading schedule...",
        schedule_error: "Failed to load schedule.",

        // Language Selection
        selectLanguage: "Please select your language.",
        continue: "Continue",
        english: "English",
        tagalog: "Tagalog",

        // product-info page
        packageProducts: "Package Products",
        loadingPackages: "Loading packages...",
        loadMore: "Load More",
        noPackagesInCategory: "No packages in this category",
        selectOtherCategory: "Please select another category",
        confirmed: "Confirmed Departure",
        expand_intro: "Expand Introduction",
        collapse_intro: "Collapse Introduction"
    },

    tl: {
        // Navigation at Common UI
        home: "Tahanan",
        mypage: "Aking Pahina",
        reservation: "Reserbasyon",
        schedule: "Iskedyul",
        profile: "Profile",
        settings: "Mga Setting",
        logout: "Mag-logout",
        login: "Mag-login",
        signup: "Mag-signup",
        back: "Bumalik",
        next: "Susunod",
        confirm: "Kumpirmahin",
        cancel: "Kanselahin",
        save: "I-save",
        edit: "I-edit",
        delete: "Tanggalin",
        book_now: "Mag-book Ngayon",

        // Permissions Page
        appPermissions: "Mga Pahintulot ng App",
        permissionsRequired: "Ang mga sumusunod na pahintulot ay kinakailangan para sa optimal na serbisyo.",
        location: "Lokasyon",
        locationDesc: "Magbigay ng customized na travel products at guide services base sa kasalukuyang lokasyon",
        camera: "Camera",
        cameraDesc: "Mag-register ng profile photos at kumuha ng mga larawan sa paglalakbay",
        notification: "Mga Notification",
        notificationDesc: "Tumanggap ng mga mahalagang notification para sa booking confirmations, schedule changes, at event updates",
        storage: "Storage",
        storageDesc: "I-save ang mga travel documents at larawan, mag-download ng offline maps",
        required: "Kinakailangan",
        optional: "Opsyonal",
        permissionsNote: "Ang mga pahintulot ay maaaring mabago sa settings anumang oras.",

        // Home Page
        todaysTrip: "Trip Ngayon",
        upcomingTrip: "Paparating na Trip",
        bySeason: "Ayon sa Season",
        byRegion: "Ayon sa Rehiyon",
        byTheme: "Ayon sa Theme",
        seeAll: "Tingnan lahat",
        aboutUs: "Tungkol sa amin",
        companyIntro: "Company Info",
        companyIntroTitle: "Pagpapakilala ng Kumpanya",
        companyIntroDescription: "Ang Smart Travel ay isang kumpanya ng package travel service na nagta-target sa mga Filipino customer. Sa negosyong ito, plano naming bumuo ng isang smart system para sa pamamahala at pag-akit ng mga turista mula sa Pilipinas, na may mas advanced na functionality at design renewal, upang mas magamit ito nang komportable ng mga user.",
        brandPhilosophy: "Brand & Philosophy",
        partnership: "Partnership",
        partnershipInformation: "Impormasyon sa Partnership",
        partnershipMainTitle: "Main Title",
        partnershipSubTitle: "Sub Title",
        partners: "Mga Kaakibat",
        partnershipInfo: "Partnership",

        // Travel Related
        travelDay: "Travel Day 1",
        meetingLocation: "Meeting Location",
        meetingTime: "Meeting Time",
        scheduleDetail: "Mga Detalye ng Schedule",
        guideLocation: "Lokasyon ng Guide",
        reservationDetail: "Mga Detalye ng Reserbasyon",
        fullTravelSchedule: "Buong Travel Schedule",
        history: "Kasaysayan",
        noLocationHistory: "Walang kasaysayan ng lokasyon na available.",

        // Footer
        privacyPolicy: "Privacy Policy",
        termsOfUse: "Terms of Use",
        customerService: "Customer Service",
        domestic: "Domestic",
        overseas: "Overseas",
        businessHours: "(Mon-Fri 09-18)",
        representative: "Representative",
        address: "Address",
        businessRegistrationNumber: "Business Registration Number",
        telemarketingRegistrationNumber: "Telemarketing Registration Number",
        email: "Email",
        fax: "FAX",
        privacyOfficer: "Privacy Officer",
        tourismBusinessRegistrationNumber: "Tourism Business Registration Number",
        registrationOffice: "Registration Office",
        copyright: "COPYRIGHT ⓒ SMART TRAVEL ALL RIGHTS RESERVED.",
        // Permission Modal
        serviceRequiresPermission: "This service requires permission to proceed",
        locationOptional: "Location (Optional)",
        locationDesc: "Used for providing nearby information and services",
        notificationOptional: "Notification (Optional)",
        notificationDesc: "Push notification alerts",

        // My Page
        loginPrompt: "Mag-login at<br>magplano ng susunod na trip",
        welcomeMessage: "Maligayang pagdating, {userName}!",
        loginSignup: "Mag-login/Mag-signup",
        accountSetting: "Mga Setting ng Account",
        packageTours: "Package Tours",
        bySeason: "Ayon sa Season",
        byRegion: "Ayon sa Rehiyon",
        byTheme: "Ayon sa Theme",
        private: "Private",
        dayTrip: "Day Trip",
        recentActivity: "Mga Kamakailang Aktibidad",
        manageMyTrips: "Pamahalaan ang Aking mga Trip",
        reservationHistory: "Kasaysayan ng Reserbasyon",
        upcoming: "Paparating",
        past: "Nakaraan",
        cancelled: "Nakansela",
        haveQuestions: "May mga tanong ba kayo?",
        customerSupport: "Suporta ng Customer",
        visaApplicationHistory: "Kasaysayan ng Visa Application",
        // 예약 내역 관련 추가 텍스트
        noReservationHistory: "Walang kasaysayan ng reserbasyon.",
        noUpcomingTrips: "Wala pang paparating na mga trip",
        noCompletedTrips: "Walang natapos na mga trip",
        noCancelledBookings: "Walang nakanselang mga reserbasyon",
        planNewTrip: "Magplano ng bagong trip",
        browseProducts: "Tingnan ang mga Produkto",
        loadingReservationHistory: "Naglo-load ng kasaysayan ng reserbasyon...",
        loadBookingHistoryFailed: "Nabigo sa pag-load ng kasaysayan ng reserbasyon.",
        settingsSupport: "Mga Setting at Suporta",
        notice: "Notice",
        customerCenter: "Customer Center",

        // 문의 페이지
        inquiry: "Magtanong",
        inquiryHistory: "Kasaysayan ng mga Tanong",
        callSupport: "Tawag sa Suporta",
        weekdaysHours: "Mga Araw ng Linggo 9 AM - 6 PM",
        totalInquiries: "Kabuuang 4",
        productInquiry: "Tanong sa Produkto",
        pendingReply: "Naghihintay ng Sagot",
        replied: "Nasagot na",
        inquiryTitle: "Pamagat ng Tanong",

        // 문의 페이지 추가 텍스트
        loadInquiriesFailed: "Nabigo sa pag-load ng mga tanong.",
        loadingInquiries: "Naglo-load ng mga tanong...",
        newInquiry: "Bagong Tanong",
        noInquiries: "Walang mga tanong.",
        noPendingInquiries: "Walang mga tanong na naghihintay.",
        noInProgressInquiries: "Walang mga tanong na ginagawa.",
        noResolvedInquiries: "Walang mga tanong na nasagot.",
        noClosedInquiries: "Walang mga tanong na sarado.",
        inquiriesUnit: "",

        // 문의 작성 페이지
        oneOnOneInquiry: "1:1 Inquiry",
        replyEmailAddress: "Email Address para sa Sagot",
        replyPhoneNumber: "Numero ng Telepono para sa Sagot",
        inquiryType: "Uri ng Tanong",
        selectInquiryType: "Piliin ang uri ng tanong",
        option1: "Option 1",
        option2: "Option 2",
        option3: "Option 3",
        inquiryContent: "Nilalaman ng Tanong",
        enterContent: "Ilagay ang nilalaman",
        fileAttachment: "Kalakip na File",
        upload: "I-upload",
        fileUploadLimit: "* Hanggang 5 na larawan at file ang maaaring i-upload",
        fileFormatLimit: "* JPG, JPEG, PNG, GIF, PDF format na file lamang ang pinapayagan, at bawat file ay dapat na mas mababa sa 10MB.",
        register: "Magparehistro",
        enterValidInfo: "Pakipasok ang wastong impormasyon.",
        enterInquiryContent: "Pakipasok ang nilalaman ng tanong.",
        submitting: "Sinusumite...",
        inquirySubmitted: "Matagumpay na naisumite ang tanong.",
        inquirySubmitFailed: "Nabigo sa pagsusumite ng tanong.",
        networkError: "May naganap na network error. Pakisubukan muli.",
        inquirySuccessTitle: "Inquiry Submission Complete",
        inquirySuccessDesc: "Susuriin ito ng aming team at babalikan ka.",

        // 비자 내역 페이지
        visaApplicationHistory: "Kasaysayan ng Visa Application",
        inadequateDocuments: "Kulang na Dokumento",
        duringExamination: "Sa Pagsusuri",
        completionIssuance: "Na-issue",
        rebellion: "Tinanggihan",
        loadVisaHistoryFailed: "Nabigo sa pag-load ng kasaysayan ng visa application.",
        loadingVisaHistory: "Naglo-load ng kasaysayan ng visa application...",
        newVisaApplication: "Bagong Visa Application",
        noVisaHistory: "Walang kasaysayan ng visa application.",
        
        // 알림 페이지
        noNotifications: "Walang mga notification.",
        view_details: "Tingnan ang Detalye",
        loading: "Naglo-load ng mga notification...",
        markAllAsRead: "Markahan Lahat bilang Nabasa",
        markAllAsReadConfirm: "Gusto ninyo bang markahan ang lahat ng notification bilang nabasa?",
        allNotificationsMarkedRead: "Lahat ng notification ay minarkahan bilang nabasa.",
        markAsReadFailed: "Nabigo sa pag-mark bilang nabasa.",
        markAsReadError: "May naganap na error habang mina-mark bilang nabasa.",
        noInadequateVisas: "Walang mga visa na kulang ang dokumento.",
        
        // 비자 발급 완료 페이지
        visaApplicationNotFound: "Hindi mahanap ang impormasyon ng visa application.",
        visaNotYetIssued: "Hindi pa natatapos ang pag-issue ng visa.",
        failedToLoadVisaApplication: "Nabigo sa pag-load ng impormasyon ng visa application.",
        errorLoadingVisaApplication: "May naganap na error habang naglo-load ng impormasyon ng visa application.",
        errorInitializingVisaPage: "May naganap na error habang naglo-load ng page.",
        visaInformation: "Impormasyon ng Visa",
        visaType: "Uri ng Visa",
        validPeriod: "Panahon ng Pagiging Valid",
        applicationNumber: "Numero ng Application",
        processing: "Pinoproseso...",
        downloadFailed: "Nabigo ang Download",
        visaFileNotFound: "Hindi mahanap ang visa file.",
        errorDownloadingVisa: "May naganap na error habang nagdo-download ng visa. Pakisubukan muli mamaya.",
        errorDisplayingVisaInfo: "May naganap na error habang nagpapakita ng impormasyon ng visa.",
        noUnderReviewVisas: "Walang mga visa na nasa pagsusuri.",
        noApprovedVisas: "Walang mga visa na naaprubahan.",
        noRejectedVisas: "Walang mga visa na tinanggihan.",

        // 공지사항 페이지
        notice: "Notice",
        noNotices: "Walang mga notice.",
        loadingNotices: "Naglo-load ng mga notice...",
        loadNoticesFailed: "Nabigo sa pag-load ng mga notice.",
        general: "Pangkalahatan",
        booking: "Reserbasyon",
        payment: "Bayad",
        visa: "Visa",
        system: "Sistema",
        author: "May-akda",
        viewCount: "Mga View",
        priorityHigh: "Mataas",
        priorityMedium: "Katamtaman",
        priorityLow: "Mababa",

        // Login Page
        logIn: "Mag-login",
        email: "Email",
        password: "Password",
        passwordPlaceholder: "8-12 karakter, kasama ang mga titik/numero/espesyal na karakter",
        autoLogin: "Auto Login",
        findId: "Hanapin ang ID",
        findPassword: "Hanapin ang Password",
        changePassword: "Palitan ang Password",
        signUp: "Mag-signup",
        noMemberInfo: "Walang impormasyon ng miyembro na tumutugma sa ipinasok na detalye.",
        searching: "Naghahanap...",

        // Join Page
        name: "Pangalan",
        phone: "Telepono",
        phonePlaceholder: "Ilagay ang mga numero lamang nang walang '-'",
        passwordConfirm: "Kumpirmahin ang Password",
        passwordConfirmPlaceholder: "Kumpirmahin ang Password",
        duplicateCheck: "Suriin ang Duplicate",
        // Sign Up Complete popup
        joinSuccessTitle: "Registration has been completed.",
        joinSuccessDesc: "Please log in",
        agreeAll: "Sumang-ayon sa Lahat",
        privacyCollection: "Pagkolekta at Paggamit ng Personal na Impormasyon (Kailangan)",
        privacyThirdParty: "Pagbibigay ng Personal na Impormasyon sa Ikatlong Partido (Kailangan)",
        marketingConsent: "Pagsang-ayon sa Paggamit ng Marketing (Opsiyonal)",
        join: "Mag-signup",
        invalidEmailFormat: "Mali ang format ng email.",
        invalidPhoneFormat: "Mali ang format ng telepono.",
        invalidPasswordFormat: "Mali ang format ng password.",
        passwordRule: "8-12 karakter, kasama ang mga titik, numero, at espesyal na karakter",
        passwordMismatch: "Hindi magkatugma ang mga password.",

        // Account Settings Page
        accountSettings: "Mga Setting ng Account",
        editMemberInfo: "I-edit ang Impormasyon ng Miyembro",
        changePassword: "Palitan ang Password",

        // Profile Edit Page
        editProfile: "I-edit ang Profile",
        updateProfile: "I-update ang Profile",
        profileUpdated: "Matagumpay na na-update ang profile.",
        profileUpdateFailed: "Hindi ma-update ang profile.",
        profileLoadFailed: "Hindi ma-load ang impormasyon ng profile.",
        profileLoadError: "May error na naganap habang naglo-load ng profile.",
        profileUpdateError: "May error na naganap habang nag-u-update ng profile.",
        loginRequired: "Kailangan mag-login.",
        enterAllRequiredFields: "Pakipasok ang lahat ng kinakailangang impormasyon.",
        saving: "Nagse-save...",

        // Password Change Page
        currentPassword: "Kasalukuyang Password",
        newPassword: "Bagong Password",
        confirmNewPassword: "Kumpirmahin ang Bagong Password",
        passwordChanged: "Matagumpay na napalitan ang password.",
        passwordChangeFailed: "Hindi ma-palitan ang password.",
        incorrectCurrentPassword: "Mali ang kasalukuyang password.",
        passwordChangeError: "May error na naganap habang nagpa-palit ng password.",

        // Schedule Page
        travel_schedule: "Iskeedyul ng Paglalakbay",
        guide_location: "Lokasyon ng Gabay",
        itinerary_details: "Mga Detalye ng Itinerary",
        timeline_view: "Tingnan ang Timeline",
        certified_guide: "Sertipikadong Gabay",
        departure_notice: "Mangyaring dumating sa tamang oras para sa pag-alis. Hanapin kami sa kaliwang alley ng plaza. Hanapin ang dilaw na flag bus.",
        registered: "Nakarehistro",
        no_schedule_today: "Walang nakaplanong iskeedyul para sa petsang ito.",
        schedule_loading: "Naglo-load ng iskeedyul...",
        schedule_error: "Hindi ma-load ang iskeedyul.",

        // Language Selection
        selectLanguage: "Piliin ang inyong wika.",
        continue: "Magpatuloy",
        english: "English",
        tagalog: "Tagalog",

        // product-info page
        packageProducts: "Mga Produkto ng Package",
        loadingPackages: "Naglo-load ng mga package...",
        loadMore: "Mag-load ng Higit Pa",
        noPackagesInCategory: "Walang mga package sa kategoryang ito",
        selectOtherCategory: "Mangyaring pumili ng ibang kategorya",
        confirmed: "Kumpirmadong Pag-alis",
        expand_intro: "Palawakin ang Introduksyon",
        collapse_intro: "I-collapse ang Introduksyon",
        
        // settings page
        settings: "Mga Setting",
        language: "Wika",
        thirdPartySharing: "Pagbabahagi ng Impormasyon sa Ikatlong Partido",
        marketingPreferences: "Mga Kagustuhan sa Marketing",
        logoutConfirm: "Gusto mo bang mag-logout?",
        logoutDescription: "Matapos mag-logout, madidirekta ka sa home screen.",
        logout: "Mag-logout",
        deleteConfirm: "Talaga bang gusto mong mag-withdraw?",
        deleteDescription: "Lahat ng impormasyon ay mabubura at hindi na mababawi kapag nag-withdraw.",
        deleteAccount: "Tanggalin ang Account",
        cannotDelete: "Hindi maaaring mag-withdraw",
        deleteRestrictDescription: "Limitado ang pag-withdraw dahil may kasalukuyang naka-book na trip.",
        deleteComplete: "Natapos na ang pag-withdraw ng membership.",
        deleteAccountError: "May naganap na error habang nag-withdraw ng membership."
    }
};

// Backward compatibility / NO Korean policy:
// Some pages still reference globalLanguageTexts.ko as a fallback.
// Ensure "ko" never renders Korean by aliasing it to English.
try { globalLanguageTexts.ko = globalLanguageTexts.en; } catch (_) {}

// 현재 언어 가져오기
function getCurrentLanguage() {
    // Source of truth:
    // - If user already chose a language (localStorage), it MUST win.
    // - URL param is treated as a hint/bootstrap only when storage is missing/invalid.
    const urlParams = new URLSearchParams(window.location.search);
    const urlLang = String(urlParams.get('lang') || '').toLowerCase();

    let savedLang = null;
    try { savedLang = String(localStorage.getItem("selectedLanguage") || '').toLowerCase(); } catch (_) { savedLang = null; }
    const hasSaved = (savedLang === 'en' || savedLang === 'tl');
    const hasUrl = (urlLang === 'en' || urlLang === 'tl');

    if (hasSaved) return savedLang;
    if (hasUrl) {
        try { localStorage.setItem("selectedLanguage", urlLang); } catch (_) {}
        return urlLang;
    }
    return "en";
}

// =========================================================
// NO Korean policy (User)
// - Site supports ONLY English/Tagalog.
// - As a safety net, remove Hangul characters from visible text/attributes
//   after language apply, so Korean never appears even if some templates
//   still contain hard-coded Korean.
// =========================================================
function __stripHangulChars(s) {
    const orig = String(s || '');
    if (!/[가-힣]/.test(orig)) return orig;
    const cleaned = orig.replace(/[가-힣]+/g, '').replace(/\s{2,}/g, ' ').trim();
    // IMPORTANT:
    // We should never inject a generic "error-like" message into normal page content.
    // If a string is entirely Hangul (cleaned becomes empty), keep the original text
    // and rely on proper translations/content management to remove Korean at source.
    // Alert/confirm are handled separately by patchUserPopupToEnglish().
    return cleaned && cleaned.length ? cleaned : orig;
}

function __scrubHangulInDom(rootEl) {
    try {
        const root = rootEl || document.body || document.documentElement;
        if (!root) return;

        // Policy update:
        // - "No Korean" applies to UI strings (i18n-managed), NOT DB content.
        // - Therefore we only scrub nodes that are explicitly i18n-marked.
        const isI18nUiEl = (el) => {
            try {
                if (!el || !el.closest) return false;
                return !!el.closest('[data-i18n],[data-i18n-placeholder],[data-i18n-title],[data-i18n-alt],[data-lan-eng],[data-lan-tl],[data-lan-ko],[data-lan-kor]');
            } catch (_) {
                return false;
            }
        };

        // Text nodes
        try {
            const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
            const nodes = [];
            while (walker.nextNode()) nodes.push(walker.currentNode);
            for (const n of nodes) {
                const v = n?.nodeValue;
                if (v && /[가-힣]/.test(v)) {
                    // Do not scrub content explicitly marked as "skip" (e.g., itinerary content)
                    try {
                        const p = n.parentElement || n.parentNode;
                        if (p && p.closest && p.closest('[data-skip-hangul-scrub="1"]')) continue;
                    } catch (_) {}
                    // Never touch script/style text to avoid breaking JS/CSS.
                    try {
                        const p2 = n.parentElement;
                        if (p2 && (p2.tagName === 'SCRIPT' || p2.tagName === 'STYLE')) continue;
                    } catch (_) {}
                    // Only scrub i18n-managed UI text (leave DB content as-is)
                    try {
                        const p3 = n.parentElement;
                        if (!isI18nUiEl(p3)) continue;
                    } catch (_) { continue; }
                    n.nodeValue = __stripHangulChars(v);
                }
            }
        } catch (_) {}

        // Common attributes (avoid touching user-entered values)
        try {
            const attrs = ['placeholder', 'title', 'aria-label', 'alt'];
            root.querySelectorAll('*').forEach((el) => {
                if (!el || !el.getAttribute) return;
                // Do not scrub content explicitly marked as "skip"
                try { if (el.closest && el.closest('[data-skip-hangul-scrub="1"]')) return; } catch (_) {}
                // Only scrub i18n-managed UI attributes (leave DB content as-is)
                if (!isI18nUiEl(el)) return;
                for (const a of attrs) {
                    const v = el.getAttribute(a);
                    if (!v || !/[가-힣]/.test(v)) continue;
                    if (a === 'alt') el.setAttribute(a, 'Image');
                    else if (a === 'placeholder') el.setAttribute(a, 'Please enter a value.');
                    else el.setAttribute(a, __stripHangulChars(v));
                }
            });
        } catch (_) {}
    } catch (_) {}
}

// Popup i18n safety-net (User): alert/confirm must never show Korean.
(function patchUserPopupToEnglish() {
    if (window.__userPopupEnglishPatched) return;
    window.__userPopupEnglishPatched = true;

    const hasKorean = (s) => /[가-힣]/.test(String(s || ''));
    const __alert = window.alert ? window.alert.bind(window) : (m) => void m;
    const __confirm = window.confirm ? window.confirm.bind(window) : () => true;

    const translateAlert = (msg) => {
        let s = String(msg ?? '');
        if (!hasKorean(s)) return s;
        s = s.replace(/\s+/g, ' ').trim();
        const rules = [
            [/^로그인이 필요합니다\.?$/g, 'Login required.'],
            [/^필수 항목을 모두 입력해주세요.*$/g, 'Please fill in all required fields.'],
            [/^올바른 이메일 형식을 입력해주세요.*$/g, 'Please enter a valid email address.'],
        ];
        for (const [re, out] of rules) if (re.test(s)) return out;
        return 'Please check the information.';
    };

    const translateConfirm = (msg) => {
        let s = String(msg ?? '');
        if (!hasKorean(s)) return s;
        s = s.replace(/\s+/g, ' ').trim();
        const rules = [
            [/삭제하시겠습니까\??/g, 'Are you sure you want to delete?'],
            [/로그아웃하시겠습니까\??/g, 'Do you want to log out?'],
        ];
        for (const [re, out] of rules) if (re.test(s)) return out;
        return 'Are you sure?';
    };

    window.alert = (m) => __alert(translateAlert(m));
    window.confirm = (m) => __confirm(translateConfirm(m));
})();

// MutationObserver safety-net: scrub Korean text inserted dynamically after load.
(function observeForKorean() {
    if (window.__i18nKoreanObserver) return;
    window.__i18nKoreanObserver = true;
    let t = null;
    const schedule = () => {
        if (t) return;
        t = setTimeout(() => {
            t = null;
            try { __scrubHangulInDom(document.body); } catch (_) {}
        }, 50);
    };
    try {
        const obs = new MutationObserver(() => schedule());
        const start = () => {
            try {
                if (!document.body) return;
                obs.observe(document.body, { childList: true, subtree: true, characterData: true, attributes: true });
                schedule();
            } catch (_) {}
        };
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
        else start();
    } catch (_) {}
})();

// 언어 변경
function changeLanguage(lang) {
    if (!['en', 'tl'].includes(lang)) lang = 'en';
    localStorage.setItem("selectedLanguage", lang);
    updatePageLanguage(lang);
}

// 페이지 언어 업데이트 (각 페이지에서 구현)
function updatePageLanguage(lang) {
    const texts = globalLanguageTexts[lang] || globalLanguageTexts.en;

    // data-i18n 속성을 가진 모든 요소 업데이트
    const elements = document.querySelectorAll('[data-i18n]');
    elements.forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (texts[key]) {
            element.textContent = texts[key];
        }
    });

    // data-i18n-placeholder 속성을 가진 모든 요소의 placeholder 업데이트
    const placeholderElements = document.querySelectorAll('[data-i18n-placeholder]');
    placeholderElements.forEach(element => {
        const key = element.getAttribute('data-i18n-placeholder');
        if (texts[key]) {
            element.placeholder = texts[key];
        }
    });

    // 특정 ID로 언어 업데이트
    updateSpecificElements(texts);
}

// 특정 요소들 업데이트 (각 페이지에서 필요에 따라 확장)
function updateSpecificElements(texts) {
    // 공통 요소들
    const title = document.querySelector('title');
    if (title && title.textContent.includes('스마트 트래블')) {
        if (texts === globalLanguageTexts.en) {
            title.textContent = title.textContent.replace('스마트 트래블', 'Smart Travel');
        } else if (texts === globalLanguageTexts.tl) {
            title.textContent = title.textContent.replace('스마트 트래블', 'Smart Travel');
        }
    }
}

// 언어별 텍스트 가져오기
function getText(key, lang = null) {
    const language = lang || getCurrentLanguage();
    const texts = globalLanguageTexts[language] || globalLanguageTexts.en;
    return texts[key] || key;
}

// API 경로 결정 함수
function getApiPath() {
    if (window.location.pathname.includes('/user/')) {
        return '../backend/api/i18n.php';
    }
    return 'backend/api/i18n.php';
}

// 서버에서 다국어 텍스트 로드
async function loadServerTexts(languageCode = null) {
    try {
        const lang = languageCode || getCurrentLanguage();
        const apiPath = getApiPath();
        const url = `${apiPath}?action=get_texts&lang=${lang}`;
        
        // CORS 및 네트워크 오류 처리
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            },
            cache: 'no-cache'
        });
        
        if (!response.ok) {
            console.warn(`API 요청 실패: ${response.status} ${response.statusText}`);
            console.warn(`요청 URL: ${url}`);
            return {};
        }
        
        const text = await response.text();
        let result;
        
        try {
            result = JSON.parse(text);
        } catch (parseError) {
            console.warn('JSON 파싱 실패:', parseError);
            console.warn('응답 내용:', text.substring(0, 200) + '...');
            return {};
        }
        
        if (result.success) {
            // 서버에서 받은 텍스트를 기존 텍스트와 병합
            Object.assign(globalLanguageTexts[lang], result.data);
            console.log(`다국어 텍스트 로드 성공: ${lang} (${Object.keys(result.data).length}개)`);
            return result.data;
        } else {
            console.warn('서버에서 다국어 텍스트를 로드할 수 없습니다:', result.message);
            return {};
        }
    } catch (error) {
        console.warn('다국어 텍스트 로드 실패:', error);
        // 네트워크 오류 시 기본 텍스트 사용
        console.log('기본 다국어 텍스트를 사용합니다.');
        return {};
    }
}

// 패키지 다국어 정보 로드
async function loadPackageI18n(packageId, languageCode = null) {
    try {
        const lang = languageCode || getCurrentLanguage();
        const apiPath = getApiPath();
        
        const response = await fetch(`${apiPath}?action=get_package_i18n&package_id=${packageId}&lang=${lang}`);
        const result = await response.json();
        
        if (result.success) {
            return result.data;
        } else {
            console.warn('패키지 다국어 정보를 로드할 수 없습니다:', result.message);
            return null;
        }
    } catch (error) {
        console.warn('패키지 다국어 정보 로드 실패:', error);
        return null;
    }
}

// 패키지 목록 다국어 정보 로드
async function loadPackagesI18n(languageCode = null, category = null, limit = 10, offset = 0) {
    try {
        const lang = languageCode || getCurrentLanguage();
        const apiPath = getApiPath();
        let url = `${apiPath}?action=get_packages_i18n&lang=${lang}&limit=${limit}&offset=${offset}`;
        
        if (category) {
            url += `&category=${category}`;
        }
        
        const response = await fetch(url);
        const result = await response.json();
        
        if (result.success) {
            return result.data;
        } else {
            console.warn('패키지 목록 다국어 정보를 로드할 수 없습니다:', result.message);
            return [];
        }
    } catch (error) {
        console.warn('패키지 목록 다국어 정보 로드 실패:', error);
        return [];
    }
}

// 공지사항 다국어 정보 로드
async function loadNoticeI18n(noticeId, languageCode = null) {
    try {
        const lang = languageCode || getCurrentLanguage();
        const apiPath = getApiPath();
        const response = await fetch(`${apiPath}?action=get_notice_i18n&notice_id=${noticeId}&lang=${lang}`);
        const result = await response.json();
        
        if (result.success) {
            return result.data;
        } else {
            console.warn('공지사항 다국어 정보를 로드할 수 없습니다:', result.message);
            return null;
        }
    } catch (error) {
        console.warn('공지사항 다국어 정보 로드 실패:', error);
        return null;
    }
}

// 사용자 언어 설정 저장
async function saveUserLanguage(accountId, languageCode) {
    try {
        const formData = new FormData();
        formData.append('action', 'set_user_language');
        formData.append('account_id', accountId);
        formData.append('language_code', languageCode);
        
        const apiPath = getApiPath();
        const response = await fetch(apiPath, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('사용자 언어 설정이 저장되었습니다.');
            return true;
        } else {
            console.warn('사용자 언어 설정 저장 실패:', result.message);
            return false;
        }
    } catch (error) {
        console.warn('사용자 언어 설정 저장 실패:', error);
        return false;
    }
}

// 사용자 언어 설정 조회
async function getUserLanguage(accountId) {
    try {
        const apiPath = getApiPath();
        const response = await fetch(`${apiPath}?action=get_user_language&account_id=${accountId}`);
        const result = await response.json();
        
        if (result.success) {
            return result.data.preferredLanguage;
        } else {
            console.warn('사용자 언어 설정을 조회할 수 없습니다:', result.message);
            return 'en';
        }
    } catch (error) {
        console.warn('사용자 언어 설정 조회 실패:', error);
        return 'en';
    }
}

// 언어 변경 (서버 연동)
async function changeLanguageWithServer(lang, accountId = null) {
    if (!['en', 'tl'].includes(lang)) lang = 'en';
    // 로컬 스토리지에 저장
    localStorage.setItem("selectedLanguage", lang);
    
    // 서버에서 최신 텍스트 로드
    await loadServerTexts(lang);
    
    // 페이지 언어 업데이트
    updatePageLanguage(lang);
    
    // 사용자 언어 설정 저장 (로그인된 사용자인 경우)
    if (accountId) {
        await saveUserLanguage(accountId, lang);
    }
    
    // 페이지 새로고침 (필요한 경우)
    if (typeof window.location !== 'undefined') {
        // 현재 페이지가 언어 선택 페이지가 아닌 경우에만 새로고침
        if (!window.location.pathname.includes('index.html')) {
            window.location.reload();
        }
    }
}

// 페이지 로드 시 자동으로 언어 적용
document.addEventListener('DOMContentLoaded', async function() {
    const root = document.documentElement;

    const currentLang = getCurrentLanguage();
    try {
        root.setAttribute('data-lang', currentLang);
        root.lang = currentLang;
    } catch (e) {
        // ignore
    }

    // 1) 즉시(로컬 번역) 적용 후 화면 표시: 깜빡임 방지 + 공백 최소화
    try {
        updatePageLanguage(currentLang);
        // safety-net: Korean must never be visible
        __scrubHangulInDom(document.body);
    } catch (e) {
        console.warn('페이지 언어 업데이트 실패(로컬):', e);
    } finally {
        try {
            root.setAttribute('data-i18n-ready', '1');
            root.removeAttribute('data-i18n-pending');
        } catch (e) {
            // ignore
        }
    }

    // 2) 서버 텍스트는 백그라운드로 갱신 (초기 표시를 지연시키지 않음)
    try {
        await loadServerTexts(currentLang);
        updatePageLanguage(currentLang);
        __scrubHangulInDom(document.body);
    } catch (e) {
        // ignore (fallback to local texts)
    }

    // 3) 로그인 사용자 언어(서버 저장)를 적용할 필요가 있을 때만 사용
    // - 이미 localStorage에 선택 언어가 있으면(사용자가 설정한 값) 초기 언어가 바뀌지 않도록 유지
    let savedLang = null;
    try { savedLang = localStorage.getItem('selectedLanguage'); } catch (_) {}
    const hasSavedLang = !!savedLang && ['en', 'tl'].includes(savedLang);

    if (!hasSavedLang) {
        const sessionData = localStorage.getItem('sessionData');
        if (sessionData) {
            try {
                const session = JSON.parse(sessionData);
                if (session.user && session.user.id) {
                    const userLang = await getUserLanguage(session.user.id);
                    if (userLang && ['en', 'tl'].includes(userLang) && userLang !== currentLang) {
                        localStorage.setItem("selectedLanguage", userLang);
                        await loadServerTexts(userLang);
                        updatePageLanguage(userLang);
                        __scrubHangulInDom(document.body);
                        try {
                            root.setAttribute('data-lang', userLang);
                            root.lang = userLang;
                        } catch (_) {}
                    }
                }
            } catch (error) {
                console.warn('사용자 세션 정보를 파싱할 수 없습니다:', error);
            }
        }
    }
});

// 글로벌 스코프에 함수들 노출
window.globalLanguageTexts = globalLanguageTexts;
window.getCurrentLanguage = getCurrentLanguage;
window.changeLanguage = changeLanguage;
window.changeLanguageWithServer = changeLanguageWithServer;
window.updatePageLanguage = updatePageLanguage;
window.getText = getText;
window.loadServerTexts = loadServerTexts;
window.loadPackageI18n = loadPackageI18n;
window.loadPackagesI18n = loadPackagesI18n;
window.loadNoticeI18n = loadNoticeI18n;
window.saveUserLanguage = saveUserLanguage;
window.getUserLanguage = getUserLanguage;