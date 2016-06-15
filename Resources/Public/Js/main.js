window.onload = function() {
    if (window.jQuery) {
        documentReady();
    } else {
        // jQuery is not loaded
        console.log('loading jQuery externally');
        var script = document.createElement('SCRIPT');
        script.src = 'https://ajax.googleapis.com/ajax/libs/jquery/2.2.1/jquery.min.js';
        script.type = 'text/javascript';
        document.getElementsByTagName('head')[0].appendChild(script);
        //jquery is loaded -> fire jQuery code after 100ms
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


        ////TODO: include facebook javascript api and update likes and comments via ajax
        //// FACEBOOK AJAX => LIKES/COMMENTS COUNTER UPDATE
        ////
        //$('.pb-list-item-facebook').each(function(){
        //    var _PostID = $(this).data('id');
        //
        //
        //    var likes = $(this).find('.info-1').text().replace(/(\r\n|\n|\r)/gm,"").replace(/\s+/g,"");;
        //    var comments = $(this).find('.info-2').text().replace(/(\r\n|\n|\r)/gm,"").replace(/\s+/g,"");;
        //
        //    if(likes == '70+'){
        //        var _LikeLink = $(this).find('.info-1');
        //        $.ajax({ url: 'https://graph.facebook.com/'+_PostID+'/likes?limit=5000' }).done(function( data ) {
        //            _LikeLink.text(data.data.length);
        //        });
        //    }
        //    if(comments == '70+'){
        //        var _CommentLink = $(this).find('.info-2');
        //        $.ajax({ url: 'https://graph.facebook.com/'+_PostID+'/comments?limit=5000' }).done(function( data ) {
        //            _CommentLink.text(data.data.length);
        //        });
        //    }
        //});

        //
        // TODO => RANDOMIZE CLASSES AS ABP WORKAROUND
        //

}