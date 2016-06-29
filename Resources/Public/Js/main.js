window.onload = function() {
    if (window.jQuery) {
        initBindings();
    } else {
        // jQuery is not loaded
        console.log('loading jQuery externally');
        createScript('https://ajax.googleapis.com/ajax/libs/jquery/2.2.1/jquery.min.js');
        //jquery is loaded -> fire jQuery code after 333ms
        setTimeout(function(){initBindings()},333);
    }
};


function documentReady(){

    jQuery('a.likes,a.comments,a.plus,a.replies').click(function(){
        window.open(this.href,'_blank','width=1200,height=800');
        return false;
    });

    //
    // ANY CLICK REDIRECT TO SOURCE OBJECT PAGE
    //
    jQuery('.pb-list-item .image, .pb-list-item .icon, .pb-list-item img, .pb-list-item .text').click(function(e){
        var _Url = jQuery(this).closest('.pb-list-item').data('url');
        window.open(_Url,'_blank','width=1200,height=800');
        return false;
    });

    // facebook reactions on hover
    jQuery('.pb-list-item-facebook').each(function(){
        var thiz = jQuery(this);
        thiz.find('.info-1').hover(
            function(){ thiz.find('.fb-like-details').addClass('active'); },
            function(){ thiz.find('.fb-like-details').removeClass('active'); }
        );
    });

}

function initLoadMore(){

    var _button = jQuery('#load_more_posts'), _Url = _button.data('url'), _show_on_click = _button.data('limit'), _postUrls = [];
    _button.addClass('loading');
    // generate list of already shown posts
    jQuery('.pb-list-item').each(function(){ _postUrls.push(jQuery(this).data('url')); });
    // request new posts
    jQuery.ajax({ url: _Url }).done(function( data ) {
        var _pb_list = jQuery(data).find('.pb-list');
        _pb_list.find('.pb-list-item').each(function(){
            if(jQuery.inArray(jQuery(this).data('url'), _postUrls) == -1){
                var _post = jQuery(this);
                _post.addClass('pb-hide-initial');
                jQuery('.pb-list').append(_post);
            }
        });
        // ladeanimation beenden
        documentReady();
        _button.removeClass('loading');
    });

    // show loaded posts on click
    _button.click(function(){
        // ladeanimation
        _button.addClass('loading');
        jQuery('.pb-hide-initial').each(function(i){
            if(i < _show_on_click) jQuery(this).fadeIn().removeClass('pb-hide-initial');
            else return false;
        });
        if(jQuery('.pb-hide-initial').length > 0) _button.removeClass('loading');
        else _button.addClass('pb-disabled');
    });
}

function initBindings(){
    documentReady();
    initLoadMore();
}

//// HELPER FUNCTIONS ////
function createScript(src){
    var script = document.createElement('SCRIPT');
    script.src = src;
    script.type = 'text/javascript';
    document.getElementsByTagName('head')[0].appendChild(script);
}

