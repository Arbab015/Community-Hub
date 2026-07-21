/**
 * UI Carousel
 */

'use strict';

(function() {
  const galleryThumbs = document.querySelector('.gallery-thumbs'),
    galleryTop = document.querySelector('.gallery-top');
  let galleryInstance;


  // Gallery effect
  // --------------------------------------------------------------------
  if (galleryThumbs) {
    galleryInstance = new Swiper(galleryThumbs, {
      spaceBetween: 10,
      slidesPerView: 4,
      freeMode: true,
      watchSlidesVisibility: true,
      watchSlidesProgress: true
    });
  }

  if (galleryTop) {
    new Swiper(galleryTop, {
      spaceBetween: 10,
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev'
      },
      thumbs: {
        swiper: galleryInstance
      }
    });
  }
})();
