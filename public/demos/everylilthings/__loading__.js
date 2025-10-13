pc.script.createLoadingScreen(function (app) {
    var showSplash = function () {
        // splash wrapper
        var wrapper = document.createElement('div');
        wrapper.id = 'application-splash-wrapper';
        document.body.appendChild(wrapper);

        // splash
        var splash = document.createElement('div');
        splash.id = 'application-splash';
        wrapper.appendChild(splash);
        splash.style.display = 'block';

        var container = document.createElement('div');
        container.id = 'progress-bar-container';
        splash.appendChild(container);

 var intro = document.createElement('div');
    intro.innerText='loading . . . ';
       intro.id = 'txtintro';
         wrapper.appendChild(intro);
     
        var bar = document.createElement('div');
        bar.id = 'progress-bar';
        wrapper.appendChild(bar);

    };

    var hideSplash = function () {
        var splash = document.getElementById('application-splash-wrapper');
        splash.parentElement.removeChild(splash);
    };

    var setProgress = function (value) {
        var bar = document.getElementById('progress-bar');
        if(bar) {
            value = Math.min(1, Math.max(0, value));
            bar.style.width = value * 100 + '%';
        }
    };

    var createCss = function () {
        var css = [
            'body {',
            '    background-color: #03111a;',
            '}',

            '#application-splash-wrapper {',
            '    position: absolute;',
            '    top: 0;',
            '    left: 0;',
            '    height: 100%;',
            '    width: 100%;',
            '    background-color: #03111a;',
            '}',

             '#txtintro {',
            'display:block;',
            'font-weight: bold;',
            'font-size:48px;',
            'position: absolute;',
            'top: 45%;',
            'text-align:center;',
            'height: 50px;',
            'padding-bottom: 10px;',
            'width: 100%;',
            'color: #ffffff;',
            '    background-color: #03111a;',
            '}',

            '#application-splash {',
            '    position: absolute;',
            '    display: block;',
            '    width: 0px;',

            '    height: auto;',
            '}',

            '#application-splash img {',
            '    width: 100%;',
            '}',

            '#progress-bar-container {',
            '    margin: 20px auto 0 auto;',
            '    height: 4px;',
            '    width: 100%;',
            '    background-color: #ed4591;',
            '}',

            '#progress-bar {',
            '    width: 0%;',
            '    height: 100%;',
            '    background-color: #ed4591;',
            '}',
            '@media (max-width: 480px) {',
            '    #application-splash {',
            '        width: 170px;',
            '        left: calc(50% - 85px);',
            '    }',
            '}'
        ].join("\n");

        var style = document.createElement('style');
        style.type = 'text/css';
        if (style.styleSheet) {
            style.styleSheet.cssText = css;
        } else {
            style.appendChild(document.createTextNode(css));
        }

        document.head.appendChild(style);
    };

    createCss();
    showSplash();

    app.on('preload:end', function () {
        app.off('preload:progress');
    });
    app.on('preload:progress', setProgress);
    app.on('start', hideSplash);
});