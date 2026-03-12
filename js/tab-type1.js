    //    
  const tabs1 = document.querySelectorAll('.btn-tab1');

  tabs1.forEach(tab => {
    tab.addEventListener('click', function(e) {
      e.preventDefault(); // href="#none"   
      
      //   active 
      tabs1.forEach(t => t.classList.remove('active'));

      //   active 
      this.classList.add('active');
    });

  const sections = [document.getElementById('inquiring'), document.getElementById('inquiryDetails')];

  tabs1.forEach(tab => {
    tab.addEventListener('click', function(e) {
      e.preventDefault();

      // active  
      tabs1.forEach(t => t.classList.remove('active'));
      this.classList.add('active');

      //   
      sections.forEach(sec => sec.style.display = 'none');

      //   data-target  id 
      const targetId = this.getAttribute('data-target');
      document.getElementById(targetId).style.display = 'block';
      
      //       
      if (targetId === 'inquiryDetails' && typeof loadInquiries === 'function') {
        loadInquiries().then(() => {
          if (typeof renderInquiryList === 'function') {
            renderInquiryList();
          }
        });
      }
    });
  });

  });