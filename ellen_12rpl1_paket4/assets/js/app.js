(function () {
    var header = document.getElementById('siteHeader');
    var menuBtn = document.querySelector('.menu-btn');
    var nav = document.getElementById('mainNav');
    var toggles = document.querySelectorAll('.nav-toggle');

    function closeDropdowns(except) {
        toggles.forEach(function (btn) {
            var item = btn.parentElement;
            if (item !== except) {
                item.classList.remove('open');
                btn.setAttribute('aria-expanded', 'false');
            }
        });
    }

    window.toggleMenu = function () {
        if (!nav || !menuBtn) return;
        var open = nav.classList.toggle('open');
        menuBtn.classList.toggle('is-open', open);
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('menu-open', open);
        if (!open) closeDropdowns(null);
    };

    function closeMenu() {
        if (nav && nav.classList.contains('open')) window.toggleMenu();
    }

    toggles.forEach(function (btn) {
        btn.addEventListener('click', function (ev) {
            ev.stopPropagation();
            var item = btn.parentElement;
            var willOpen = !item.classList.contains('open');
            closeDropdowns(item);
            item.classList.toggle('open', willOpen);
            btn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });
    });

    document.addEventListener('click', function (ev) {
        if (header && !header.contains(ev.target)) {
            closeDropdowns(null);
            closeMenu();
        }
    });

    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') {
            closeDropdowns(null);
            closeMenu();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 960) {
            closeDropdowns(null);
            closeMenu();
        }
    });
})();

// Preview + validasi cover sebelum form dikirim.
(function () {
    var MAX_BYTES = 5 * 1024 * 1024;
    var TIPE = ['image/jpeg', 'image/png', 'image/webp'];

    document.querySelectorAll('input[data-cover-input]').forEach(function (input) {
        var box = input.parentElement;
        var preview = box.querySelector('[data-cover-preview]');
        var pesan = box.querySelector('[data-cover-msg]');

        function tampilkanError(teks) {
            if (pesan) pesan.textContent = teks;
            if (preview) preview.hidden = true;
            input.value = '';
        }

        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (pesan) pesan.textContent = '';
            if (preview) preview.hidden = true;
            if (!file) return;

            if (TIPE.indexOf(file.type) === -1) {
                tampilkanError('Format harus JPG, PNG, atau WEBP.');
                return;
            }

            if (file.size > MAX_BYTES) {
                var mb = (file.size / 1048576).toFixed(1).replace('.', ',');
                tampilkanError('Ukuran gambar ' + mb + ' MB, maksimal 5 MB.');
                return;
            }

            if (preview) {
                preview.src = URL.createObjectURL(file);
                preview.hidden = false;
            }
        });
    });
})();
