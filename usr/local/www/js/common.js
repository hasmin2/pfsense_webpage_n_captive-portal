$(document).ready(function(){
    resizingAct()
    mobileMenu();
    scrollY()
})

// dim 생성
function dimMaker() {
    if($('body').find('.dim').length > 0){
        return;
    }
    $('body').append('<div class="dim"></div>');
    bodyHidden();
}

// dim 제거
function dimRemove() {
    $('.dim').remove();
    bodyAuto();
}

// body scroll hidden
function bodyHidden() {
    $('body').css('overflow', 'hidden');
}

// body scroll auto
function bodyAuto() {
    $('body').css('overflow', '')
}

// 팝업열기
function popOpen(target){
    $("." + target).addClass('on');
    scrollY()
}

// 팝업닫기
function popClose(target) {
    $("." + target).removeClass('on');
    dimRemove();
}

// dim 옵션 팝업 열기
function popOpenAndDim(target, isDim){
    popOpen(target);
    
    if(isDim == true){
        dimMaker();
    }
}

function resizingAct(){
    $(window).resize(function(){
        let windowWidth = $(window).width()
        
        if(windowWidth > 1440) {
            dimRemove();
            closeMenu();
            $('.popup').removeClass('on')
        }
    })
}

function mobileMenu(){
    openMenuAct();
    closeMenuAct()
}

// 모바일 메뉴 열기
function openMenuAct(){
    $('#wrapper #sidebar .brand .btn-menu-open').click(function(){
        openMenu()
    })
}
function openMenu(){
    $('#wrapper #sidebar #lnb').addClass('on');
    dimMaker()
}

// 모바일 메뉴 열기
function closeMenuAct(){
    $('#wrapper #sidebar #lnb .btn-menu-close').click(function(){
        closeMenu()
    })
}

function closeMenu(){
    $('#wrapper #sidebar #lnb').removeClass('on');
    dimRemove()
}

function scrollY(){
    $('.scroll-y').each(function(){
        $(this).mCustomScrollbar();
    })
}