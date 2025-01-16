$(document).ready(function(){
    sortAreaStyle()
    listResponsiveStyle()
})

function listResponsiveStyle(){
    let windowWidth = $(window).width()

    $(window).resize(function(){
        windowWidth = $(window).width()

        if(windowWidth <= 1440) {
            $('.list-wrap.v1').each(function(){
                $(this).find('td').each(function(){
                    if($(this).closest('.row').length <= 0){
                        $(this).wrap('<div class="row"></div>')
                        $(this).before('<th>' + $(this).data('th') + '</th>');
        
                        let thisRow = $(this).closest('.row');
        
                        thisRow.width($(this).data('width') + '%')
                        thisRow.find('th').width($(this).data('th-width'))
                    }
                })
            })
        } else {
            $('.list-wrap.v1').each(function(){
                $(this).find('td').each(function(){
                    $(this).unwrap('.row')
                    $(this).siblings('th').remove()
                })
            })
        }
    })

    if(windowWidth <= 1440) {
        $('.list-wrap.v1').each(function(){
            $(this).find('td').each(function(){
                if($(this).closest('.row').length <= 0){
                    $(this).wrap('<div class="row"></div>')
                    $(this).before('<th>' + $(this).data('th') + '</th>');
    
                    let thisRow = $(this).closest('.row');
    
                    thisRow.width($(this).data('width') + '%')
                    thisRow.find('th').width($(this).data('th-width'))
                }
            })
        })
    } else {
        $('.list-wrap.v1').each(function(){
            $(this).find('td').each(function(){
                $(this).unwrap('.row')
                $(this).siblings('th').remove()
            })
        })
    }
}

function sortAreaStyle(){
    let sidebarHeight = $('#wrapper #sidebar').outerHeight()
    let headlineHeight = $('#wrapper #content .headline-wrap').outerHeight()
    let listHeight = $('.list-top .btn-area').length > 0 ? $('.list-top .btn-area').outerHeight() : 0;
    console.log($('.list-top').outerHeight)

    $('.list-top').css('min-height', listHeight)
    $('.list-wrap.v1 .sort-area .inner').css('top', sidebarHeight + headlineHeight + listHeight)
}