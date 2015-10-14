# MultipartEmail

Максимально простой класс для отправки почты с вложениями на PHP. Возможности:
- Отправка почты в UTF8 с кодированием в base64 всех частей сообщения
- Отправка почты с HTML и текстовой частями (alternative)
- Отправка почты со вложенными файлами (multipart)
- Отправка HTML со встроенными изображениями (embed, multipart related)

Отправка осуществляется функцией mail(), а не прямым соединением с SMTP сервером.
Класс работает с любой кодировкой входных данных: UTF-8 (по-умолчанию), Windows-1251 и т.д.

Пример использования:

    <?php
    require('MultipartEmail.php');
    $email = new MultipartEmail();
    $email->setFrom("Me <me@localhost.ru>");
    $email->setSubject("Поздравляем с отправкой почты!");
    $email->setTo("Адресат <some@one.ru>, Другой адресат <else@some.one>");
    $email->setText("Дорогие товарищи! ...");
    $email->setHtml("<h1>Дорогие товарищи!</h1> <img src=\"image.jpg\"> ...");
    $email->addAttachement('/path/to/file.jpg', 'image/jpeg', 'image.jpg', false, true);
    $email->send();
    ?>
