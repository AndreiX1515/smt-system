//     (     )
window.initHomeSlider = function initHomeSlider() {
    if (typeof $ === 'undefined' || !$.fn || !$.fn.slick) return;
    var $status = $('.slick-counter');
    var $slickElement = $('.slider');
    if (!$slickElement.length) return;

    //  slick  
    if ($slickElement.hasClass('slick-initialized')) {
        try { $slickElement.slick('unslick'); } catch (e) {}
    }

    $slickElement.off('init reInit afterChange');
    $slickElement.on('init reInit afterChange', function (event, slick, currentSlide) {
        var i = (currentSlide ? currentSlide : 0) + 1;
        if ($status.length) {
            $status.text(i + '/' + slick.slideCount);
        }
    });

    $slickElement.slick({
        dots: false,
        arrows: true,
        infinite: true,
        speed: 500,
        slidesToShow: 1,
        slidesToScroll: 1,
        autoplay: true
    });
};

$(document).ready(function(){
    //  (data-dynamic=1)     
    var $el = $('.slider');
    if ($el.length && $el.attr('data-dynamic') === '1') {
        window.addEventListener('home:banners:ready', function() {
            window.initHomeSlider && window.initHomeSlider();
        }, { once: true });
        return;
    }
    window.initHomeSlider && window.initHomeSlider();
});
