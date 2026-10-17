$( function() {

    // Changelog-Dialog hinter der Versionsanzeige (Inhalt wird bei jedem Öffnen neu geladen)
    let changelogDialog = $('<div class="changelog-dialog"></div>');
    $('a.app-version').on('click' , function( e ){
        e.preventDefault();
        changelogDialog.html('<p>Lade Changelog …</p>').dialog({
            title: 'Changelog – ' + $(this).text(),
            modal: true,
            width: Math.min( 640 , $(window).width() - 32 ),
            maxHeight: $(window).height() - 64
        });
        $.get( $(this).attr('data-changelog-url') )
            .done(function( html ){
                changelogDialog.html( html );
            })
            .fail(function(){
                changelogDialog.html('<p>Changelog konnte nicht geladen werden.</p>');
            });
    });

});
