/* ============================================================
   TRUEGIFT - custom.js
   ต้องโหลดหลัง bootstrap.bundle, sweetalert2 และ window.APP
   ============================================================ */

(function () {
    'use strict';

    const APP = window.APP || {};
    const form = document.getElementById('topupForm');
    const ref1 = document.getElementById('ref1');
    const gifturl = document.getElementById('gifturl');
    const submitBtn = document.getElementById('submitBtn');
    const submitSpinner = document.getElementById('submitSpinner');
    const submitIcon = document.getElementById('submitIcon');
    const submitText = document.getElementById('submitText');
    const pasteBtn = document.getElementById('pasteBtn');
    const statusEl = document.getElementById('apiStatus');
    const statusText = document.getElementById('apiStatusText');
    const videoModalEl = document.getElementById('videoModal');
    const tutorialVideo = document.getElementById('tutorialVideo');

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            }[ch];
        });
    }

    function swal(options) {
        return Swal.fire(Object.assign({
            confirmButtonText: 'ตกลง',
            allowOutsideClick: false
        }, options));
    }

    function setLoading(loading) {
        submitBtn.disabled = loading;
        submitSpinner.classList.toggle('d-none', !loading);
        submitIcon.classList.toggle('d-none', loading);
        submitText.textContent = loading ? 'กำลังตรวจสอบ...' : 'ตรวจสอบและเติมเงิน';
        ref1.disabled = loading;
        gifturl.disabled = loading;
        pasteBtn.disabled = loading;
    }

    function lockForm() {
        ref1.disabled = true;
        gifturl.disabled = true;
        pasteBtn.disabled = true;
        submitBtn.disabled = true;
        submitSpinner.classList.add('d-none');
        submitIcon.classList.add('d-none');
        submitText.textContent = 'ระบบไม่พร้อมใช้งาน';
    }

    function setStatus(state, message) {
        const isReady = state === 'ready';
        const isUnconfigured = state === 'unconfigured';
        const isDbError = state === 'db_error';

        statusEl.dataset.state = isReady
            ? 'ready'
            : (isUnconfigured ? 'unconfigured' : (isDbError ? 'db_error' : 'error'));
        statusText.textContent = isReady
            ? 'API พร้อมใช้งาน'
            : (isUnconfigured
                ? 'ยังไม่ได้ตั้งค่า API'
                : (isDbError ? 'ฐานข้อมูลไม่พร้อมใช้งาน' : 'API ไม่พร้อมใช้งาน'));
        statusEl.title = message || '';
        statusEl.dataset.message = message || '';
    }

    function showStatusDetail() {
        const state = statusEl.dataset.state;
        const labels = {
            ready: { icon: 'success', title: 'API พร้อมใช้งาน' },
            unconfigured: { icon: 'info', title: 'ยังไม่ได้ตั้งค่า API' },
            db_error: { icon: 'error', title: 'ฐานข้อมูลไม่พร้อมใช้งาน' },
            error: { icon: 'warning', title: 'API ไม่พร้อมใช้งาน' }
        };
        const detail = labels[state] || labels.error;

        swal({
            icon: detail.icon,
            title: detail.title,
            text: statusEl.dataset.message || 'ไม่มีรายละเอียดจาก API'
        });
    }

    function showFlash() {
        if (!APP.flash || !APP.flash.message) {
            return;
        }

        swal({
            icon: APP.flash.type === 'success' ? 'success' : 'error',
            title: APP.flash.title || (APP.flash.type === 'success' ? 'สำเร็จ' : 'ไม่สำเร็จ'),
            text: APP.flash.message
        });
    }

    function validate(username, link) {
        if (username === '') {
            return 'กรุณากรอก username หรือ userID ที่ต้องการเติมเครดิต';
        }

        if (!/^[\p{L}\p{N}._@-]{1,100}$/u.test(username)) {
            return 'ข้อมูลผู้ใช้มีอักขระที่ไม่รองรับ';
        }

        if (link === '') {
            return 'กรุณากรอกลิงก์ซองของขวัญ';
        }

        let parsed;
        try {
            parsed = new URL(link);
        } catch (error) {
            return 'ลิงก์ซองของขวัญไม่ถูกต้อง';
        }

        if (parsed.protocol !== 'https:' || parsed.hostname.toLowerCase() !== 'gift.truemoney.com') {
            return 'ลิงก์ต้องเป็น https://gift.truemoney.com/ เท่านั้น';
        }

        const voucher = parsed.searchParams.get('v') || '';
        if (!/^[A-Za-z0-9_-]{4,200}$/.test(voucher)) {
            return 'ลิงก์ไม่พบรหัสซองของขวัญ (v)';
        }

        return null;
    }

    async function updateApiStatus() {
        try {
            const response = await fetch(APP.statusUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            });
            const data = await response.json();
            setStatus(data.state || (data.ok ? 'ready' : 'error'), data.message || '');
        } catch (error) {
            setStatus('error', 'ไม่สามารถเชื่อมต่อ API ได้');
        }
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        if (APP.dbError) {
            swal({
                icon: 'error',
                title: 'ฐานข้อมูลไม่พร้อมใช้งาน',
                text: APP.dbError
            });
            return;
        }

        const username = ref1.value.trim();
        const link = gifturl.value.trim();
        const validationError = validate(username, link);

        if (validationError) {
            swal({
                icon: 'warning',
                title: 'ข้อมูลไม่ถูกต้อง',
                text: validationError
            });
            return;
        }

        const parsed = new URL(link);
        const voucher = parsed.searchParams.get('v') || '';
        const maskedLink = parsed.hostname + parsed.pathname + '?v=' + voucher.slice(0, 6) + (voucher.length > 6 ? '…' : '');

        const confirmation = await swal({
            icon: 'question',
            title: 'ยืนยันการเติมเงิน',
            html: '<div class="swal-confirm-row"><span>ผู้ใช้</span><b>' + escapeHtml(username) + '</b></div>' +
                  '<div class="swal-confirm-row"><span>ซองของขวัญ</span><b class="swal-link">' + escapeHtml(maskedLink) + '</b></div>' +
                  '<div class="swal-note">ตรวจสอบข้อมูลให้แน่ใจก่อนยืนยัน ระบบจะทำการแลกลิงก์ทันที</div>',
            showCancelButton: true,
            confirmButtonText: 'ยืนยันและเติมเงิน',
            cancelButtonText: 'ยกเลิก',
            reverseButtons: true,
            focusCancel: true,
            allowOutsideClick: false
        });

        if (!confirmation.isConfirmed) {
            return;
        }

        setLoading(true);

        Swal.fire({
            title: 'กำลังตรวจสอบซองของขวัญ',
            html: '<div class="swal-sub">กรุณารอสักครู่ ระบบกำลังแลกลิงก์กับ TrueMoney</div>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: function () {
                Swal.showLoading();
            }
        });

        const controller = new AbortController();
        const timer = setTimeout(function () {
            controller.abort();
        }, 45000);

        try {
            const body = new FormData(form);
            body.set('ajax', '1');

            const response = await fetch(form.action || 'index.php', {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                signal: controller.signal
            });

            let data;
            try {
                data = await response.json();
            } catch (error) {
                throw new Error('invalid-response');
            }

            Swal.close();

            if (!data.ok) {
                swal({
                    icon: 'error',
                    title: 'เติมเงินไม่สำเร็จ',
                    text: data.message || 'ไม่สามารถตรวจสอบซองของขวัญได้'
                });
                return;
            }

            swal({
                icon: 'success',
                title: 'เติมเงินสำเร็จ',
                html: '<div class="swal-amount">+' + escapeHtml(data.amount_text) + ' ฿</div>' +
                      '<div class="swal-sub">เครดิตคงเหลือ ' + escapeHtml(data.balance_text) + '</div>',
                confirmButtonText: 'เรียบร้อย'
            });

            gifturl.value = '';
        } catch (error) {
            Swal.close();

            swal({
                icon: 'error',
                title: 'เชื่อมต่อไม่สำเร็จ',
                text: error.name === 'AbortError'
                    ? 'การตรวจสอบใช้เวลานานเกินไป กรุณาลองใหม่อีกครั้ง'
                    : 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาลองใหม่อีกครั้ง'
            });
        } finally {
            clearTimeout(timer);
            setLoading(false);
        }
    });

    pasteBtn.addEventListener('click', async function () {
        if (!navigator.clipboard || !navigator.clipboard.readText) {
            swal({
                icon: 'info',
                title: 'วางอัตโนมัติไม่ได้',
                text: 'เบราว์เซอร์นี้ไม่อนุญาตให้อ่านคลิปบอร์ด กรุณากดวางเอง (Ctrl+V)'
            });
            gifturl.focus();
            return;
        }

        try {
            const text = (await navigator.clipboard.readText()).trim();

            if (text === '') {
                swal({
                    icon: 'info',
                    title: 'คลิปบอร์ดว่าง',
                    text: 'กรุณาคัดลอกลิงก์ซองของขวัญก่อน แล้วลองอีกครั้ง'
                });
                return;
            }

            gifturl.value = text;
            gifturl.focus();
        } catch (error) {
            swal({
                icon: 'info',
                title: 'วางอัตโนมัติไม่ได้',
                text: 'เบราว์เซอร์ไม่อนุญาตให้อ่านคลิปบอร์ด กรุณากดวางเอง (Ctrl+V)'
            });
            gifturl.focus();
        }
    });

    if (videoModalEl && tutorialVideo) {
        videoModalEl.addEventListener('hidden.bs.modal', function () {
            tutorialVideo.pause();
            tutorialVideo.currentTime = 0;
        });
    }

    statusEl.addEventListener('click', showStatusDetail);
    statusEl.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            showStatusDetail();
        }
    });

    if (APP.dbError) {
        lockForm();
        setStatus('db_error', APP.dbError);

        swal({
            icon: 'error',
            title: 'ฐานข้อมูลไม่พร้อมใช้งาน',
            text: APP.dbError
        });
    } else {
        showFlash();
        updateApiStatus();
    }
})();
