(function ( $ ) {
    console.log( 'Classyfeds assets loaded' );

    function renderListings( items ) {
        var $container = $( '.classyfeds-listings' );
        items.forEach( function ( item ) {
            var $article = $( '<article/>' ).addClass( 'classyfeds-listing' );

            if ( item.image ) {
                var $imgWrap = $( '<div/>' ).addClass( 'classyfeds-listing-image' );
                $( '<img/>' ).attr( 'src', item.image ).attr( 'alt', item.title || '' ).appendTo( $imgWrap );
                $imgWrap.appendTo( $article );
            }

            var $header = $( '<header/>' ).addClass( 'entry-header' );
            var $title = $( '<h2/>' ).addClass( 'entry-title' );
            if ( item.link ) {
                $( '<a/>' )
                    .attr( 'href', item.link )
                    .attr( 'target', '_blank' )
                    .attr( 'rel', 'nofollow noopener' )
                    .text( item.title || '' )
                    .appendTo( $title );
            } else {
                $title.text( item.title || '' );
            }
            $title.appendTo( $header );
            $header.appendTo( $article );

            if ( item.content ) {
                $( '<div/>' ).addClass( 'entry-content' ).html( item.content ).appendTo( $article );
            }

            $container.append( $article );
        } );
    }

    window.classyfedsLoad = function ( endpoint ) {
        return fetch( endpoint )
            .then( function ( res ) {
                return res.json();
            } )
            .then( function ( data ) {
                if ( data && data.orderedItems ) {
                    var items = data.orderedItems.map( function ( item ) {
                        var img = '';
                        if ( item.image ) {
                            img = item.image.url || item.image;
                        }
                        var link = '';
                        if ( item.url ) {
                            link = item.url;
                        } else if ( item.id ) {
                            link = item.id;
                        }
                        return {
                            title: item.name || '',
                            content: item.content || item.summary || '',
                            image: img,
                            link: link,
                        };
                    } );
                    renderListings( items );
                }
                return data;
            } );
    };
})( jQuery );
