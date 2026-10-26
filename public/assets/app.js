$( function() {

    // Menü am Handy auf- und zuklappen (☰ in der Kopfzeile)
    $('.nav-toggle').on('click' , function(){
        let open = $('body').toggleClass('nav-open').hasClass('nav-open');
        $(this).attr('aria-expanded' , open ? 'true' : 'false');
    });

    // Tabellen am Handy als Karten (wie im StAN-Manager): jede Zelle bekommt die Überschrift ihrer Spalte als Beschriftung
    // (data-label, Anzeige per CSS). Zeilen nur aus <th>: mehrere Zellen = Kopfzeile, eine Zelle = Zwischenüberschrift.
    // Tabellen ohne Kopfzeile und solche mit .table-keep bleiben, wie sie sind.
    $('main table').not('.table-keep').each(function(){
        let table = $(this);
        let headers = null;
        let usable = true;
        table.find('> thead > tr, > tbody > tr, > tr').each(function(){
            let row = $(this);
            let cells = row.children('th, td');
            if( cells.length === cells.filter('th').length ){
                if( cells.length === 1 ){
                    row.addClass('table-cards-section');
                } else {
                    headers = [];
                    cells.each(function(){
                        for( let i = 0 ; i < ( parseInt( $(this).attr('colspan') ) || 1 ) ; i++ ){
                            headers.push( $(this).text().replace(/\s+/g , ' ').trim() );
                        }
                    });
                    row.addClass('table-cards-head');
                }
                return;
            }
            if( headers === null ){
                usable = false;
                return false;
            }
            let column = 0;
            cells.each(function(){
                $(this).attr('data-label' , headers[ column ] ?? '');
                if( $(this).text().trim() === '' && $(this).find('img, input, select, textarea, button').length === 0 ){
                    $(this).addClass('table-cards-empty');
                }
                column += parseInt( $(this).attr('colspan') ) || 1;
            });
        });
        if( usable && headers !== null ){
            table.addClass('table-cards');
        }
    });

    // Knopf „Kopieren“: Inhalt des Feldes (data-copy = Selektor) in die Zwischenablage
    $('[data-copy]').on('click' , function(){
        let button = $(this);
        let field = $( button.attr('data-copy') );
        let done = function(){
            let label = button.text();
            button.text('Kopiert ✓').prop('disabled' , true);
            setTimeout( function(){ button.text( label ).prop('disabled' , false ); } , 2000 );
        };
        if( navigator.clipboard && window.isSecureContext ){
            navigator.clipboard.writeText( field.val() ).then( done );
        } else {
            // ohne HTTPS gibt es die Clipboard-API nicht
            field.trigger('select');
            document.execCommand('copy');
            done();
        }
    });

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
