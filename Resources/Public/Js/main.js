window.onload = function() {
    if (window.jQuery) {
        documentReady();
    } else {
        // jQuery is not loaded
        console.log('loading jQuery externally');
        createScript('https://ajax.googleapis.com/ajax/libs/jquery/2.2.1/jquery.min.js');
        //jquery is loaded -> fire jQuery code after 333ms
        setTimeout(function(){documentReady()},333);
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

//// HELPER FUNCTIONS ////
function createScript(src){
    var script = document.createElement('SCRIPT');
    script.src = src;
    script.type = 'text/javascript';
    document.getElementsByTagName('head')[0].appendChild(script);
}

