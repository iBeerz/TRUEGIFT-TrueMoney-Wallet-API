# TRUEGIFT · ระบบเติมเงินด้วยซองของขวัญ TrueWallet

ระบบเติมเงินหน้าเดียว (Single Page) สำหรับรับลิงก์ซองของขวัญ **TrueMoney Wallet**
ส่งไปตรวจสอบกับ API แล้วเพิ่มเครดิตให้ผู้ใช้โดยอัตโนมัติ

ออกแบบใหม่ด้วย **Bootstrap 5 (CDN)**, **SweetAlert2 (CDN)** และเชื่อมต่อฐานข้อมูลด้วย **PDO เท่านั้น**
ไม่มีการใช้ `mysqli` / `mysql_*` / `eval()` ในโค้ด

---

## สารบัญ

- [ภาพรวมการทำงาน](#ภาพรวมการทำงาน)
- [ฟีเจอร์](#ฟีเจอร์)
- [โครงสร้างไฟล์](#โครงสร้างไฟล์)
- [ความต้องการของเซิร์ฟเวอร์](#ความต้องการของเซิร์ฟเวอร์)
- [การติดตั้ง](#การติดตั้ง)
- [การตั้งค่า config.php](#การตั้งค่า-configphp)
- [โครงสร้างฐานข้อมูล](#โครงสร้างฐานข้อมูล)
- [วิธีใช้งาน](#วิธีใช้งาน)
- [ลำดับการทำงานของ API](#ลำดับการทำงานของ-api)
- [ความปลอดภัย](#ความปลอดภัย)
- [การแก้ไขปัญหา](#การแก้ไขปัญหา)
- [ปรับแต่งเพิ่มเติม](#ปรับแต่งเพิ่มเติม)
- [ต่อยอด: บันทึกประวัติการเติมเงิน](#ต่อยอด-บันทึกประวัติการเติมเงิน)

---

## ภาพรวมการทำงาน

1. ผู้ใช้เปิดหน้า `index.php` (สามารถแนบ username หรือ userID ผ่าน `?ref1=...` ได้)
2. ผู้ใช้กรอก username / userID และวางลิงก์ซองของขวัญจาก `gift.truemoney.com`
3. ระบบแสดง SweetAlert2 ให้ยืนยันก่อนทำรายการ
4. เซิร์ฟเวอร์ตรวจสอบข้อมูล, CSRF Token และตรวจว่ามีผู้ใช้ที่ตรงกับ `user_field` ในฐานข้อมูลจริง
5. ส่งลิงก์ไปยัง API `url_api` พร้อมข้อมูลบัญชี API
6. เมื่อ API ตอบกลับ `Status = check_success` ระบบจะนำ `Amount` ไปบวกในฟิลด์เครดิตของผู้ใช้ทันที
7. แสดงผลสำเร็จ/ผิดพลาดด้วย SweetAlert2 พร้อมยอดเงินและเครดิตคงเหลือ

> ระบบใช้ยอดเงินจากซองตรงตัว ไม่มีตัวคูณเครดิต และรองรับเฉพาะซองของขวัญ (truegift) เท่านั้น

---

## ฟีเจอร์

- หน้าเดียวจบ `index.php`
- ดีไซน์ทันสมัย รองรับมือถือ (Responsive)
- Bootstrap 5 + Bootstrap Icons + SweetAlert2 ผ่าน CDN
- ปุ่มดูวิดีโอวิธีสร้างซองของขวัญ เปิดลิงก์ **TikTok** ในแท็บใหม่
- ปุ่มวางลิงก์จากคลิปบอร์ด
- ตรวจสอบสถานะ API อัตโนมัติ (มี Cache 30 วินาที)
- ยืนยันก่อนเติมเงิน และแจ้งเตือนทุกกรณีด้วย SweetAlert2
- ใช้ PDO Prepared Statements ทั้งหมด
- ป้องกัน CSRF ด้วย Token ต่อ Session
- ตรวจสอบลิงก์ให้เป็น `https://gift.truemoney.com/...` เท่านั้น
- ไม่บันทึกลิงก์ซองของขวัญลงฐานข้อมูลหรือ Log
- แสดงเครดิตคงเหลือหลังเติมสำเร็จ

---

## โครงสร้างไฟล์

```
truegift/
├── index.php              # หน้าเว็บ + ตัวจัดการ AJAX + การเชื่อมต่อฐานข้อมูล/API
├── config.php             # ค่าตั้งค่าระบบ (ค่าเชื่อมต่อถูกล้างไว้)
├── custom.css             # สไตล์ทั้งหมดของหน้าเว็บ (แยกจาก index.php)
├── custom.js              # สคริปต์ฝั่งเบราว์เซอร์ทั้งหมด (แยกจาก index.php)
└── README.md              # เอกสารนี้
```

> ไม่มีไฟล์วิดีโอในโปรเจกต์แล้ว ปุ่ม “ดูวิดีโอวิธีสร้างซองของขวัญ”
> จะเปิดลิงก์ TikTok (`VIDEO_URL`) ในแท็บใหม่แทน

### ไฟล์ CSS / JS

- สไตล์ทั้งหมดอยู่ใน `custom.css` และสคริปต์ทั้งหมดอยู่ใน `custom.js`
- `index.php` โหลดสองไฟล์นี้พร้อมต่อท้ายเวอร์ชันจาก `filemtime()`
  เช่น `custom.css?v=1763...` เพื่อไม่ให้เบราว์เซอร์ใช้ไฟล์เก่าจาก cache
  หลังแก้ไฟล์จึงเห็นผลทันทีโดยไม่ต้องกด hard refresh
- ยังมี `<script>` ในหน้าเดียวที่จำเป็น คือ `window.APP = {...}`
  เพราะเป็นข้อมูลที่ PHP สร้างจาก config (`statusUrl`, `flash`, `dbError`)
  และ `custom.js` ต้องโหลดหลังบล็อกนี้เสมอ
- ลำดับการโหลด: SweetAlert2 → `window.APP` → `custom.js`
- ใช้ Bootstrap เฉพาะ CSS (grid, form, utilities) จึงไม่โหลด `bootstrap.bundle.min.js`
  แล้ว เพราะไม่มี Modal/Tooltip ที่ต้องใช้ JavaScript ของ Bootstrap

---

## ความต้องการของเซิร์ฟเวอร์

- PHP **8.0 ขึ้นไป** (แนะนำ 8.1+)
- Extensions: `pdo_mysql`, `curl`, `json`, `openssl`
- `mbstring` (แนะนำ แต่โค้ดมี fallback ให้)
- MySQL หรือ MariaDB
- เปิด HTTPS ในเว็บจริง (แนะนำอย่างยิ่ง)
- เซิร์ฟเวอร์ต้องเชื่อมต่ออินเทอร์เน็ตได้ เพื่อเรียก API

---

## การติดตั้ง

1. คัดลอกไฟล์ทั้งหมดไปยังโฟลเดอร์เว็บของคุณ เช่น `C:\laragon\www\truegift`
2. เปิด `config.php` แล้วกรอกข้อมูล API และฐานข้อมูลให้ครบ
3. สร้างฐานข้อมูลและตารางผู้ใช้ (ดูหัวข้อถัดไป)
4. ตรวจสอบว่า `custom.css` และ `custom.js` อยู่ในโฟลเดอร์เดียวกับ `index.php`
5. เปิดเว็บ `http://localhost/truegift/index.php`
6. ทดสอบด้วยซองของขวัญจริงมูลค่าน้อย ๆ ก่อนใช้งานจริง

> ระบบไม่ต้องติดตั้ง Composer และไม่ต้อง build assets ใด ๆ เพราะใช้ CDN

---

## การตั้งค่า config.php

ไฟล์ `config.php` คืนค่าเป็น **Array** เพื่อให้เรียกใช้ได้ง่าย:

```php
return [
    'url_api' => 'https://tmwallet.thaighost.net/apiwallet.php',

    'api' => [
        'username' => '',
        'password' => '',
        'tmemail'  => '',
    ],

    'database' => [
        'host'     => '',
        'port'     => 3306,
        'name'     => '',
        'user'     => '',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'table' => [
        'users'       => 'users',
        'user_field'  => 'username',
        'point_field' => 'points',
    ],
];
```

| คีย์ | คำอธิบาย |
|---|---|
| `url_api` | ที่อยู่ API สำหรับตรวจสอบ/แลกซอง ตั้งค่าไว้ให้แล้ว |
| `api.username` | ยูสเซอร์เนมของระบบ TMWeasy |
| `api.password` | รหัสผ่านของระบบ TMWeasy |
| `api.tmemail` | เบอร์ TrueWallet ที่ผูกกับ API (ตัวเลขเท่านั้น) |
| `database.host` | โฮสต์ฐานข้อมูล เช่น `localhost` หรือ `127.0.0.1` |
| `database.port` | พอร์ต MySQL/MariaDB ปกติคือ `3306` |
| `database.name` | ชื่อฐานข้อมูล |
| `database.user` | ผู้ใช้ฐานข้อมูล |
| `database.password` | รหัสผ่านฐานข้อมูล |
| `database.charset` | Charset แนะนำ `utf8mb4` |
| `table.users` | ชื่อตารางผู้ใช้ |
| `table.user_field` | ฟิลด์ที่ใช้ **ค้นหาผู้ใช้และเพิ่มเครดิต** (ค่าเริ่มต้น `username` หรือเปลี่ยนเป็น userID เช่น `id`, `user_id`) |
| `table.point_field` | ชื่อฟิลด์เครดิต |

### การเลือก `user_field` (username หรือ userID)

`user_field` ถูกใช้ทั้งในคำสั่ง `SELECT` เพื่อค้นหาผู้ใช้ และ `UPDATE` เพื่อเพิ่มเครดิต
ค่าที่ผู้ใช้กรอกในช่อง Ref1 จะถูกนำไปเทียบกับฟิลด์นี้โดยตรง

- เว็บทั่วไปที่ใช้ชื่อผู้ใช้ login → ใช้ `username`
- ระบบใหญ่ๆ ที่อ้างอิงผู้ใช้ด้วยรหัส → เปลี่ยนเป็น `id` หรือ `user_id` ได้
- เมื่อเปลี่ยน `user_field` แล้ว ค่า `?ref1=` ก็ต้องส่งค่าแบบเดียวกัน เช่น `?ref1=10023`
- ฟิลด์ที่ใช้ต้องมีค่าไม่ซ้ำ (UNIQUE) เพื่อป้องกันเครดิตไปเพิ่มผิดคน

### หมายเหตุ

- ค่าที่เป็นความลับ (API / Database) ถูกล้างไว้โดยตั้งใจ ให้กรอกเองก่อนใช้งาน
- ชื่อตารางและฟิลด์อนุญาตเฉพาะ `A-Z a-z 0-9 _` เพื่อความปลอดภัย
- ค่า `url_api` ห้ามลบ เพราะใช้ทั้งการตรวจสถานะและการเติมเงิน
- ไม่มีการตั้งค่าฐานข้อมูลแบบเลือกชนิดแล้ว (ตัด `database_type` ออก)
- ไม่มีตัวคูณเครดิต / ไม่มีราคาบัตร truemoney / ไม่มี `$paytype`
- ไม่มีชื่อบัญชีวอเลทแล้ว (เหลือเฉพาะเบอร์ `tmemail` ที่ API ต้องใช้)

---

## โครงสร้างฐานข้อมูล

ระบบต้องมีตารางผู้ใช้ที่มีฟิลด์อ้างอิงผู้ใช้ (username หรือ userID) และฟิลด์เครดิต
ตัวอย่างโครงสร้างตารางที่ใช้งานได้ทันที:

```sql
CREATE DATABASE IF NOT EXISTS `truegift`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
  `id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `points`   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตัวอย่างผู้ใช้ทดสอบ
INSERT INTO `users` (`username`, `points`)
VALUES ('demo', 0.00);
```

จากนั้นตั้งค่าใน `config.php`:

```php
'database' => [
    'host'     => 'localhost',
    'port'     => 3306,
    'name'     => 'truegift',
    'user'     => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
],
'table' => [
    'users'       => 'users',
    'user_field'  => 'username',
    'point_field' => 'points',
],
```

> หากใช้ตารางเดิมของเว็บคุณอยู่แล้ว เพียงแก้ชื่อตาราง/ฟิลด์ให้ตรง
> ระบบจะบวกเครดิตด้วยคำสั่ง `points = points + ยอดเงิน`

### ตัวอย่างกรณีใช้ userID แทน username

หากระบบหลักอ้างอิงผู้ใช้ด้วย `id`:

```php
'table' => [
    'users'       => 'users',
    'user_field'  => 'id',
    'point_field' => 'points',
],
```

ลิงก์ที่ส่งเข้ามาต้องเป็น `?ref1=10023` (ค่าของ `id`) และช่อง Ref1 ในหน้าเว็บต้องกรอก `10023`

> ฟิลด์ที่เลือกใช้เป็น `user_field` ควรเป็น UNIQUE หรือ PRIMARY KEY
> เพื่อให้ค้นหาและเพิ่มเครดิตได้ผู้ใช้เพียงคนเดียวเท่านั้น

---

## วิธีใช้งาน

### ผู้ใช้ทั่วไป

1. เปิดแอป **TrueMoney Wallet**
2. สร้างซองของขวัญ (ดูวิธีจากปุ่มในหน้าเว็บ)
3. กดแชร์ → คัดลอกลิงก์
4. เปิดหน้าเว็บ เติม username หรือ userID และวางลิงก์
5. กด **ตรวจสอบและเติมเงิน** → ยืนยัน → รอระบบตรวจสอบ
6. รับเครดิตทันทีเมื่อสำเร็จ

### การส่ง username / userID จากเว็บหลัก

ลิงก์รูปแบบ:

```
https://your-domain.com/index.php?ref1=username
```

หรือกรณีใช้ userID:

```
https://your-domain.com/index.php?ref1=10023
```

ระบบจะเติมค่านี้ในช่อง Ref1 ให้อัตโนมัติ โดยค่านี้ต้องตรงกับฟิลด์ `user_field` ที่ตั้งไว้
แนะนำให้เว็บหลักตรวจสอบสิทธิ์ผู้ใช้ก่อน redirect มาที่หน้านี้

---

## ลำดับการทำงานของ API

### ตรวจสถานะ API

```
GET {url_api}?username={api.username}&password={api.password}&json=1
```

ตอบกลับที่ถือว่าพร้อมใช้งาน:

```json
{ "Status": "ready", "Msg": "การตั้งค่าระบบพร้อมใช้งาน" }
```

หน้าเว็บเรียกสถานะผ่าน `index.php?action=status` และคืนค่า `state` เป็น
`ready` / `unconfigured` / `error` เพื่อให้แสดงผลได้ถูกต้อง

> การเปิด `https://tmwallet.thaighost.net/apiwallet.php` ตรง ๆ จะเห็นหน้า greeting
> แต่ไม่ได้แปลว่า API พร้อมใช้งาน ต้องเรียกพร้อม `username`, `password` และ `json=1`

### แลกซองของขวัญ / เติมเงิน

```
GET {url_api}
    ?username={api.username}
    &password={api.password}
    &tmemail={api.tmemail}
    &transactionid={gift_url}
    &clientip={client_ip}
    &ref1={username_or_userid}
    &action=yes
    &json=1
```

ตอบกลับเมื่อสำเร็จ:

```json
{
  "Status": "check_success",
  "Amount": 50,
  "Type": "truegift"
}
```

ระบบจะนำ `Amount` ไปบวกในฟิลด์เครดิตของผู้ใช้ที่ตรงกับค่า `ref1`
โดยเทียบค่านี้กับฟิลด์ `user_field` (username หรือ userID ตามที่ตั้งค่า) ทันที

ตอบกลับเมื่อไม่สำเร็จ (ตัวอย่าง):

```json
{
  "Status": "check_failed",
  "Msg": "Error : ไม่สามารถตัดเงินจากลิงก์ซองนี้ได้ Voucher doesn't exist."
}
```

ระบบจะแสดง `Msg` ให้ผู้ใช้ทราบผ่าน SweetAlert2

### Endpoint ภายในหน้าเดียว

| Endpoint | วิธี | หน้าที่ |
|---|---|---|
| `index.php` | GET | แสดงหน้าเว็บ |
| `index.php?action=status` | GET (AJAX) | ตรวจสถานะ API (Cache 30 วินาที) |
| `index.php` (`form_action=topup`) | POST (AJAX/ปกติ) | ตรวจสอบและเติมเครดิต |

---

## ความปลอดภัย

ระบบมีมาตรการพื้นฐานดังนี้:

- **PDO Prepared Statements** ทุกคำสั่ง SQL และไม่ใช้ `mysqli` หรือ `eval()`
- **CSRF Token** ต่อ Session ตรวจสอบทุกการ POST
- **Validate อินพุต** ทั้งชื่อผู้ใช้และลิงก์ซอง
- **จำกัดโดเมนลิงก์** ให้เป็น `gift.truemoney.com` และต้องเป็น HTTPS
- **Escaping ฝั่ง Client** ก่อนแสดงค่าใน SweetAlert2
- **Session Cookie** เป็น `HttpOnly` + `SameSite=Lax` และตั้ง `Secure` เมื่อใช้งาน HTTPS
- **ไม่ Log ลิงก์ซอง** และไม่บันทึกข้อมูลบัตร/รหัสใด ๆ
- **ตรวจผู้ใช้ก่อนเรียก API** เพื่อไม่ให้ซองถูกแลกไปโดยที่ไม่มีบัญชีปลายทาง

### คำแนะนำเพิ่มเติมสำหรับProduction

1. เปิด HTTPS ทุกครั้ง
2. จำกัดสิทธิ์ผู้ใช้ฐานข้อมูลเป็นเฉพาะ `SELECT` / `UPDATE` ตารางที่จำเป็น
3. ห้ามเปิดเผย `config.php` ผ่านเว็บ (แม้ PHP จะประมวลผลอยู่แล้วก็ตาม)
4. พิจารณาเพิ่ม Rate Limit ต่อ IP / ต่อผู้ใช้ เพื่อกันการยิงถี่
5. ตรวจสอบสิทธิ์ผู้ใช้จากระบบหลักก่อนอนุญาตให้เติมเครดิต แทนการเชื่อถือ `ref1` จาก URL อย่างเดียว
6. สำรองฐานข้อมูลก่อนใช้งานจริง
7. เปลี่ยนรหัส API และรหัสฐานข้อมูลเป็นค่าที่คาดเดายาก

---

## การแก้ไขปัญหา

### หน้าเว็บแสดง “ยังไม่ได้ตั้งค่า API”

แปลว่า `api.username`, `api.password` หรือ `api.tmemail` ใน `config.php` ยังว่างอยู่
ระบบจะยังไม่เรียก API จริงจนกว่าจะกรอกครบทั้ง 3 ค่า

### หน้าเว็บแสดง “API ไม่พร้อมใช้งาน”

- ตรวจ `url_api` ใน `config.php`
- ตรวจ `api.username`, `api.password`, `api.tmemail` ว่ากรอกครบและถูกต้อง
- ทดสอบเปิด URL ตรวจสถานะด้วยข้อมูลจริง:
  `{url_api}?username=...&password=...&json=1`
- ตรวจว่าเซิร์ฟเวอร์เปิด `curl`, เชื่อมต่ออินเทอร์เน็ตได้ และ SSL ใช้งานได้
- คลิกที่ป้ายสถานะบนหน้าเว็บเพื่อดูข้อความจาก API
- สถานะถูก Cache 30 วินาที หากเพิ่งแก้ config ให้รอหรือรีเฟรชหน้า

### หน้าเว็บแสดง “ฐานข้อมูลไม่พร้อมใช้งาน”

`index.php` จะทดสอบเชื่อมต่อฐานข้อมูลทุกครั้งที่เปิดหน้า และตอบกลับ HTTP 503
พร้อมปิดฟอร์มไม่ให้เติมเงิน หากเชื่อมต่อไม่ได้

สาเหตุที่พบบ่อย:

- ยังไม่ได้กรอก `database.host`, `database.name` หรือ `database.user`
- MySQL/MariaDB ไม่ได้เปิดอยู่
- ชื่อฐานข้อมูลหรือรหัสผ่านไม่ถูกต้อง
- ผู้ใช้ฐานข้อมูลไม่มีสิทธิ์เข้าถึงฐานข้อมูลนั้น
- โฮสต์/พอร์ตไม่ถูกต้อง หรือไฟร์วอลล์บล็อก

ข้อความละเอียดจะถูกเขียนไว้ใน `error_log` ของ PHP เพื่อไม่ให้เปิดเผยข้อมูลฐานข้อมูลบนหน้าเว็บ

### ขึ้น “ระบบยังไม่ได้ตั้งค่าฐานข้อมูล”

กรอก `database.host`, `database.name`, `database.user` ให้ครบ
(รหัสผ่านว่างได้ถ้าฐานข้อมูลไม่ได้ตั้งรหัส)

### ขึ้น “ไม่พบผู้ใช้ที่ระบุในระบบ”

- ตรวจ username หรือ userID ให้ตรงกับค่าจริงในตาราง
- ตรวจ `table.user_field` ว่าชี้ไปฟิลด์ที่ใช้ค้นหาจริง (`username`, `id`, `user_id` ฯลฯ)
- ค่า `ref1` ที่ส่งเข้ามาต้องเป็นค่าเดียวกันกับฟิลด์นั้น
- ตรวจว่าผู้ใช้มีอยู่ในฐานข้อมูลก่อนเติม

### ขึ้น “รูปแบบข้อมูลตอบกลับจาก API ไม่ถูกต้อง”

- API อาจตอบ HTML หรือข้อความธรรมดา (เช่น maintenance)
- ตรวจ `error_log` ของ PHP เพื่อดู Response ล่าสุด
- ตรวจว่า `url_api` ยังใช้งานได้

### ขึ้น “ลิงก์ต้องเป็น gift.truemoney.com เท่านั้น”

ลิงก์ต้องมีรูปแบบเช่น

```
https://gift.truemoney.com/campaign/?v=XXXXXXXXXX
```

### ซองถูกแลกไปแล้ว

- ซองของขวัญแต่ละลิงก์ใช้ได้ครั้งเดียว
- หาก API ตอบ `Voucher doesn't exist` หรือซองถูกใช้แล้ว ระบบจะไม่เพิ่มเครดิต

### ปุ่มดูวิดีโอไม่เปิดหน้า TikTok

- ปุ่มนี้เป็นลิงก์ `<a target="_blank">` ตรงไปยังค่าคงที่ `VIDEO_URL` ใน `index.php`
  ไม่ได้ใช้ JavaScript หรือ Modal แล้ว
- ตรวจว่า `VIDEO_URL` ยังเป็นลิงก์ TikTok ที่ถูกต้อง
- หากเบราว์เซอร์บล็อก pop-up ให้ลองอนุญาต pop-up สำหรับเว็บนี้
- ตรวจว่าอุปกรณ์เชื่อมต่ออินเทอร์เน็ตและเข้าถึง tiktok.com ได้

### ต้องการดู Error แบบละเอียด

ใน `index.php` ช่วงต้นไฟล์ เปลี่ยน

```php
ini_set('display_errors', '0');
```

เป็น `'1'` ชั่วคราว แล้วอย่าลืมเปลี่ยนกลับเมื่อใช้งานจริง

---

## ปรับแต่งเพิ่มเติม

- ชื่อระบบ / คำโปรย: แก้ค่า `APP_NAME`, `APP_TAGLINE` ต้นไฟล์ `index.php`
- ลิงก์ท้ายเว็บ: แก้ `FOOTER_URL`, `FOOTER_TEXT`
- ลิงก์ TikTok: แก้ `VIDEO_URL` (ปุ่มจะเปิดลิงก์นี้ในแท็บใหม่)
- ธีมสี: แก้ตัวแปร CSS ใน `:root` ภายใน `custom.css` (`--brand`, `--brand-2`, ฯลฯ)
- สไตล์อื่น ๆ ทั้งหมด: แก้ใน `custom.css` (ไม่มี `<style>` ใน `index.php` แล้ว)
- ลอจิกฝั่งเบราว์เซอร์ (ตรวจฟอร์ม, เรียก AJAX, SweetAlert2): แก้ใน `custom.js`
- ค่าคงที่ที่ใช้ทั้ง PHP และ JS (`statusUrl`, `flash`, `dbError`): แก้ `$appData` ใน `index.php`

---

## ต่อยอด: บันทึกประวัติการเติมเงิน

ตัวระบบปัจจุบัน **ไม่ได้เก็บประวัติการเติมเงินลงฐานข้อมูล** โดยตั้งใจ
เพื่อไม่ให้เก็บข้อมูลอ่อนไหวเกินจำเป็น (เก็บเฉพาะยอดเครดิตในตารางผู้ใช้)
และเขียนเพียงบรรทัดสรุปไว้ใน `error_log` ของ PHP เช่น

```text
[topup] success user=demo amount=50.00
```

ถ้าต้องการต่อยอดเป็นระบบที่มี **ประวัติการเติมเงิน / เก็บข้อมูลการเติมเงิน**
เพื่อให้ผู้ใช้หรือแอดมินตรวจสอบย้อนหลังได้ สามารถทำได้ดังนี้

### 1. สร้างตารางประวัติ

```sql
CREATE TABLE topup_history (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_ref      VARCHAR(100)    NOT NULL,          -- ค่าเดียวกับ user_field ตอนเติม
    amount        DECIMAL(12,2)   NOT NULL,          -- ยอดที่ได้รับจากซอง
    balance_after DECIMAL(12,2)   NULL,              -- เครดิตคงเหลือหลังเติม
    voucher_hash  CHAR(64)        NOT NULL,          -- sha256 ของลิงก์ซอง ใช้กันซองซ้ำ
    api_status    VARCHAR(30)     NOT NULL,          -- สถานะจาก API เช่น check_success
    client_ip     VARCHAR(45)     NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_voucher (voucher_hash),
    KEY idx_user (user_ref),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> `UNIQUE KEY uniq_voucher` ช่วยกันการบันทึกซองเดิมซ้ำ และเป็นเกราะอีกชั้น
> ไม่ให้ซองเดียวกันถูกนำไปเติมเข้าบัญชีอื่นได้ แม้ API จะตรวจให้อยู่แล้ว

### 2. เขียนบันทึกใน `process_topup()`

ใน `index.php` หลัง `UPDATE` เครดิตและอ่านยอดคงเหลือสำเร็จ
ให้เพิ่มการ `INSERT` ประวัติ (ใช้ PDO Prepared Statement เหมือนส่วนอื่น)

```php
$history = $pdo->prepare(
    "INSERT INTO topup_history
        (user_ref, amount, balance_after, voucher_hash, api_status, client_ip)
     VALUES
        (:user_ref, :amount, :balance_after, :voucher_hash, :api_status, :client_ip)"
);

try {
    $history->execute([
        ':user_ref'      => $username,
        ':amount'        => number_format($amount, 2, '.', ''),
        ':balance_after' => number_format($balance, 2, '.', ''),
        ':voucher_hash'  => hash('sha256', $giftUrl),
        ':api_status'    => (string) ($response['Status'] ?? ''),
        ':client_ip'     => client_ip(),
    ]);
} catch (PDOException $e) {
    // 23000 = ชน unique (ซองนี้เคยถูกบันทึกแล้ว)
    error_log('[topup] history failed: ' . $e->getMessage());
}
```

ถ้าต้องการให้ “เพิ่มเครดิต” และ “บันทึกประวัติ” สำเร็จหรือล้มเหลวพร้อมกัน
ให้ห่อทั้งสองคำสั่งด้วย transaction

```php
$pdo->beginTransaction();

try {
    $update->execute();
    $history->execute();
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

### 3. แสดงประวัติให้ผู้ใช้ / แอดมิน

```php
$stmt = $pdo->prepare(
    "SELECT amount, balance_after, created_at
       FROM topup_history
      WHERE user_ref = :user_ref
      ORDER BY id DESC
      LIMIT 20"
);
$stmt->execute([':user_ref' => $username]);
$rows = $stmt->fetchAll();
```

นำ `$rows` ไปวนแสดงเป็นตาราง BOOTSTRAP (`table table-dark table-sm`)
หรือสร้างหน้าแยก เช่น `history.php` ที่ตรวจสอบสิทธิ์ก่อนแสดง

### 4. ข้อควรระวัง

- **อย่าเก็บลิงก์ซองเต็ม** ให้เก็บเป็น `hash('sha256', ...)` หรือเก็บเฉพาะ 4 ตัวท้าย
  เพราะลิงก์คือกุญแจที่ใช้แลกเงินได้จริง
- เก็บ `amount` เป็น `DECIMAL` ไม่ใช่ `FLOAT` เพื่อความแม่นยำทางบัญชี
- ควรมี `created_at` และ index ไว้สำหรับรายงาน/กรองตามช่วงวันที่
- กำหนดระยะเวลาเก็บข้อมูลและสิทธิ์เข้าถึงให้ชัดเจน (เช่น เฉพาะแอดมิน)
  ตามหลัก PDPA หากเก็บข้อมูลส่วนบุคคล
- ถ้าต้องการเก็บ “กรณีล้มเหลว” ด้วย ให้เพิ่มคอลัมน์ `api_message`
  และบันทึกใน `catch` ของ `handle_topup()`

---

## เครดิต

- Frontend: Bootstrap 5, Bootstrap Icons, SweetAlert2 (CDN)
- Payment API: [TMWeasy.com API Payment](https://www.tmweasyapi.com/)
- ออกแบบและปรับปรุงโค้ดใหม่ด้วย PDO + Single Page Architecture
