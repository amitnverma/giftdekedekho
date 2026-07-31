MindAR target compiler — install notes
======================================

This folder holds the Node toolchain that turns a customer's photo into a
MindAR ".mind" image target for the Living Photo AR frame feature. PHP cannot
do this itself: the MindAR compiler is a JS/npm tool.

node_modules/ is NOT committed (it contains a platform-specific native binary),
and the deploy workflow only does `git reset --hard` with no build step. So this
install is a ONE-TIME manual step per server, repeated only when package.json
changes.


1. Requirements
---------------
  * Node.js 18 or newer  (`node -v`)
  * npm                  (`npm -v`)

Nothing else. The `canvas` dependency installs from a prebuilt binary, so no
cairo/pango system packages are needed on normal Linux and macOS hosts.

IMPORTANT: package.json pins an npm `overrides` entry for canvas ^3. MindAR
itself depends on canvas ^2, which has no prebuilt binary for modern Node and
fails to compile from source. Do not remove that override.


2. Install
----------
    cd tools/mindar-compile
    npm ci

`npm ci` installs exactly what package-lock.json specifies. Verify it works:

    cd /path/to/site
    node tools/mindar-compile/compile.mjs images/1.jpg /tmp/test.mind

You should see a line beginning with __GDD_RESULT__ and containing
"ok":true along with a trackability score. Delete /tmp/test.mind afterwards.


3. Choose how PHP calls it
--------------------------
The admin panel supports two modes, selected by `ar_compiler_mode` in
config/local.php. Default is "shell".

MODE A — "shell" (preferred, simplest, and the default)
    PHP shells out to `node compile.mjs`. Requires shell_exec or proc_open to
    be enabled in php.ini.

    You normally do NOT need to configure a path. Node is located
    automatically: PATH first, then the newest nvm install under the account's
    home directory, then the usual system locations.

    That search exists because PHP-FPM does not read .bashrc, so an nvm install
    is invisible to it even though `node -v` works fine over SSH. Installing
    Node with nvm as the site user is therefore enough on its own:

        curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash
        export NVM_DIR="$HOME/.nvm" && . "$NVM_DIR/nvm.sh"
        nvm install 20
        cd tools/mindar-compile && npm ci

    Admin -> AR Frame Orders names the binary it resolved, so you can confirm
    which Node the website is actually using. Override it only if that choice
    is wrong:

        // config/local.php
        return [
            'ar_node_binary' => '/usr/bin/node',   // output of `which node`
        ];

MODE B — "http" (for hosts where shell_exec is disabled)
    Run server.mjs as a long-lived localhost service and let PHP call it over
    HTTP. Never bind this to a public interface.

        // config/local.php
        return [
            'ar_compiler_mode'  => 'http',
            'ar_compiler_url'   => 'http://127.0.0.1:9077',
            'ar_compiler_token' => 'a-long-random-string',
        ];

    Then run the service with a matching token. Example systemd unit:

        [Unit]
        Description=GiftDekeDekho MindAR compiler
        After=network.target

        [Service]
        WorkingDirectory=/home/giftdekedekho/htdocs/www.giftdekedekho.com/tools/mindar-compile
        ExecStart=/usr/bin/node server.mjs
        Environment=GDD_AR_TOKEN=a-long-random-string
        Environment=GDD_AR_PORT=9077
        Restart=always
        User=giftdekedekho

        [Install]
        WantedBy=multi-user.target

    Check it with:  curl http://127.0.0.1:9077/health

Admin → AR Frames shows which mode is active and whether it is currently
working, so you can confirm the setup without generating a real target.


4. Browser-side libraries (vendored, not from a CDN)
---------------------------------------------------
The scan page loads MindAR and three.js from public/js/vendor/mindar, NOT from a
CDN. Those files are committed, because the deploy workflow has no build step.

Regenerate them after changing the mind-ar or three version:

    node tools/mindar-compile/vendor-assets.mjs

This copies the dist files and rewrites their bare "three" imports to relative
paths. Two reasons that matters, both of which caused real silent failures:

  * A CDN import requires the phone to have working internet on whatever Wi-Fi
    it is on. This is a camera page people open from a printed card.
  * Resolving a bare "three" specifier from a CDN requires an import map, which
    requires iOS 16.4+.

If either failed, the page's module never executed, so the "Start camera" button
had no click handler and tapping it did nothing at all, with no error shown.

three is pinned to 0.155.0 on purpose. mind-ar 1.2.5 imports sRGBEncoding, which
later three releases removed, and newer three also splits itself into
three.module.js + three.core.js. vendor-assets.mjs refuses to run if the
installed three is incompatible, rather than producing a broken bundle.


5. Diagnosing a frame that will not scan
----------------------------------------
Three tools, cheapest first:

    node tools/mindar-compile/verify-match.mjs <target.mind> <photo>
        Can this target be recognised at all? Searches the whole image, so it is
        more generous than a real camera. A failure here is conclusive; a pass is
        necessary but not sufficient.

    node tools/mindar-compile/simulate-scan.mjs <target.mind> <photo>
        What a phone actually does. MindAR does not search the full frame — it
        searches a crop of 2^round(log2(min(w,h)/2)) pixels, cycling through 9
        positions one per frame. On a 1280x720 camera that is a 256x256 window,
        so the photo must fill a large central part of the view. This reports the
        smallest share of the frame that still matches.

    /scan/{slug}?debug=1
        Live overlay on the real page: whether the library loaded, whether the
        camera started, its resolution, the crop size, and how many times the
        tracker has locked on. Use this when a recipient reports that nothing
        happens — it distinguishes a load failure from a permission failure from
        a genuine tracking problem.


6. Notes
--------
  * A single 1024px photo takes roughly 4-5 seconds to compile. That is fast
    enough to run synchronously while a walk-in customer waits at the counter.
  * Photos larger than 1024px on the long edge are scaled down first. This is
    MindAR's own recommendation — bigger images compile far slower with no
    tracking benefit.
  * Photos with no usable features (flat, blurry, very low contrast) are
    rejected outright rather than producing a target that can never match.
