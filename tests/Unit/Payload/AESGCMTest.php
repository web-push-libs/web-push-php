<?php

declare(strict_types=1);

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Minishlink\Tests\Unit\Payload;

use function chr;
use Minishlink\WebPush\Base64Url;
use Minishlink\WebPush\Utils;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AESGCMTest extends TestCase
{
    /**
     * @test
     *
     * @see https://tests.peter.sh/push-encryption-verifier/
     */
    public function decryptPayloadCorrectly(): void
    {
        $authToken = Base64Url::decode('pi5Ij6OiiCHeCSYwn8trqw==');
        $uaPublicKey = Base64Url::decode('BD9ebHQdeYX/XdvfKlzpcsKgWMTuQ2BPvhW6odeNSAonjMMBjqoVG2OriVOlIge1RAn3sCSLOK24BnRrHXrXvIA=');

        $salt = Base64Url::decode('6I0LInJRxjDzVa2_mACQJA');
        $body = Base64Url::decode('p0QY5bmUusCQ0joHnmkeIdo7jW92GniDWCiG_hdtbDN2APj2J5D7r2Lpn9FUwbhbfQp7MMpvi35zZ3XK9HtqxufkRIBYo9X89nf0SO-vNwTOdp9RuXO8kV4-X42O8q6Jz6chwtX7o-m8moFL-nQRNxyHckFpddwwxX38xlsITMT84oGWcyeB2klMmD5P5dDH8JODCuFV2zbvMQvOKHpF6kIcqD8XYwEAn2RVBI3iUuptYLKfizKxGFn40wLA2RF7Lo_oWcLYPipAdiOxGU4df086AANjJGPlz0eoFlrLdLZB9n8f68w1wRa-UlasMIrChIK01e070r3Hp1WPAY8c8uZJ61nynkyAzbFbT3eaWQJLsYldWNqJHxTazz8lobZj8ru-sKZa_NQnEC-Ngporz2-0aJoJHbq8iezp_lkhtuLbwgKthRnnK9_AAEguoy1zkAVTtZ0WU21qWga1x68BLAvgmQhoMsetg4mwQ5FKYe7AydkkDekTo4E9j-X_WLHGq_IOpJKiKHB5T7j9EjcCEnVJDLOws9TMDbzyofpqReXgZl3z7NTTWqLH9xvY7zU5HMHs1VUVWHIOCGXSacRnTEzHqkPZDw8LrIe-IYUr3d8ffqDNVlWhrlPmxYmuIMlKoAyqPlfrdqeF0oDXyECbvl4jZn5NrfPr9Vggo-mCiSwhyQBU0CqF8NTrNMXNF2GgON4c7C_w5NzBU0rui7Ye2i49-YAGcevtiSoPetI0kclSxL_FUOX3k2b7FP64Dl7ToELOpfcRDRmdtd6GkGijNOAIMmjrhmUIEeC5V4TLdA32liMblXwh6qxcZ0ThefjDiyYd4ArY4A08XtYihDQwLtRR5i5i4boBUcXOkcXX4raK-SiIbR7nwE67udKGGARMP20gc0IdYG8FCFbUH7plSy9_Pn_fVw1JnbSOU61FC1kwEK9x8FaHXLeccQ4RKgKfP6geM7OBPMlD6PTUXTXCfmjsPWnOK1suMb-XKcGLej2TYv5ShHPtb_tHOkm5gHYZLxbxlP65MkHJtpujhavXitQ-SxYTvnd0GExYSAh1bo7JFhsKMuCNqs386tcFdRQFJDz8Rm_2otXjPNDmfJByz6t15bgsa62s3lwQsZjXYhTrBmwpL1XSobkjPZn2SJScYpiHbpm9SNc1bJfo3cEkYB6pNFd_pUjuhJe8zfaXeIOZuAv6Ob0Cq3OY2ogfNn2x3A4X7kr4SObGzhDbkZBMyZfDG1jFAEwfod5I5skD7I82YyYnBPI_hlxGk1xJjawBcNbNqcEEJsZpOKTWYiThtg0XJhs_RornDl_BIaw6gE6KtDG38HK5YaSVFU-mFKjrKJxb7XF1cARFLEnoeoY4iBfYxhoiZurrjYBzv17WwzjDjBCBZSsjujdw-eEwpvAVbzjAczjb9vtlqVqti5hzWN79S-fKeNjKvHu9ovblqZVW5t-BpMYXku0Uyah2d9KSupayhsXSRZAC3aBmPAn3Gn1NszOeGL9g344EW8NR8TuK_iybbwR04gBq5p7u1Do4Z04U5Yr9zAwNZOgKpXdNsPZDuiNKULBcNxbzn2pvMQ7WVerDebssXDaErRKPMs-YtDW-zVQtoZ_tRnAkWFDGv4Fw22JhBq6aSMmGVYHhKV9_JV_ONjSOQCJQCPFR3gtwCeeqEjEvgNRpzFY2Efo39bw0gJtrklPxJUQFXqRH9v4WAaHDNOuZ5fiwxby4xr3tjNz5OGDSVK1PiZp0JMndnOKp2IgITQ4IjcmDV8PPgrgkqpQiEDrJdhe0W8aD1Dr_uLPCg7r8HKNNkI9D-nMR4L9hME7x9ZSTfNL_2r-M4noY5DBm0QqASdHlEZGkVWpXEdXPBoYVjfUfskg3PdpN2qELBGtK9D4cIMz-79JbSofZj7Wi5wLtX3si07uFiCkmnkYrHqeDBdIPOhGSVWZwY4Bk5GfUcKXqWIqdI9ehvE5DRlTYjvYP0vgWlCRlQRRye6g4Il5ObLldNZ2nrjQ0TLh2S2zkfLs_kUOVmwcX0ctLxUFTecXsGMEh6T5mGHGcjAkOptE-a3sFVmQzdY8UNCUFivzqcw3Tq5c7ZLluzi2XnoJYYEmi3nlverU9RODSCRuxlAEWg5bsghd_zZfViXMx5GpijW5EqtgH4TA0wocDFkDncgKWV-Ptv0IvXL5VFa5p9zHUjdg3Xf3KB1qbGyqsZcR8P9Xucjo49OzxkxiouivAXv-qxN6b9nVuzUcd_AVhqumnsXr802IXRpLyUHaXm_v0KlCRp8jJZQDL95swAH2blQdWFeHIqNLF4G-3uMr8lZXQ6r3YjtmQkIiWwsjs25e373NCtNiJ6o1c64yEqnqx-r6jUyyRGouMosvX5YSALhlb5VaRMmR2mLDQpDNMrQfwt-XJSyMiZlAeeVnw4KylYJ04gpnBaBTKuhNhGFFMzz4IqS3lbs1crM3ZhS3MOiEbfjiMfL0DSW-oQ92CJ0s1FG9TlB8R7Tc_BL5LCkZc8Oa7q8ko0mr6txZ5pr6JYs12T8xysjtu2q84GBwrHKDQlBDXDlKe0ClofNbsEzHuhUGf-qV_FOMjw27FPuvF9Unt_EisAlDW1vAq9oT9y8SJGblyBmCS0LHDNZcqg4tM1ZeOI3Qvq5A9OqKSLw-exn6DapeAK3Mf-ZV1dcp-t6IeWcTU73RlheU14BEES1_vNauO3JlghugmD9v9RUhkRSKrE1fuMMQtgP5RNshAoZ-ECA9xyla231RS7jOee0jVopf3etRWBdWcpvXNxjYGhbLTrm1wP6Vemlt8v85qo-Tqj50yz4QcOiN_U3FMh3SWOf7XuHOAQd3-DyAdKmJHHar95kil2Og8xW84Rsnyl-qldaBc_MhlV-R1F-TQ3iEASz1IT49rqavm0Y35j1qhyllPZRQnHe_YVk4BmUlrfHcZDTqYgR4x9X0odFSexqavrx5IFsln5W4U3ky7-QmbkTuFcemoTUdm1OjrREKvV4_AChn2_yK414uSBw_ErHSfE5Rcvgc3dLbitAOf7G_AY6ti6ZJ8G8c1cokiTfJqYSFj6o8hEn7W_1JoDuu1W1YjkbcRS8uL5DE2lk2gPR5hFMTF7lSUlzUvT3aETqd8kH_rBLhX4fwjHuc5VwVIMVqzFgbq-EG2rKeV7ZblMFhuTD3t4p175QFu-BnxpEO9ErfPMqDkYluDLgjsi5Ppr9Ac-zw8WYz2Y45kOsNVP8iC2h6TlvHZpaRi091hRZZGKjT1w_8_pwEFw9O8Z4zyZBhcF8q717mAOlIndHyikK7_qN94LHpwES7K7RgI6r2BTbkkCpoWBCGwc095C7omUA1xU-xxX4MxSk39wnHun2ZQuw9ouzBizCL01BqFi-XFXM94DmHgvP6mCfA6Hkl709xRCg6AOsvd-1VrQmIRtTOhPi53oOFiSY4af5Wq6fleYUyWZE5pbafja3TOJt7QrKszs0nMexlXV4piC-XhIlDbd2n0iQ0ay4kY6SXZTdE-HJx7WpdxeZvj10wWeJdC8jJxz2WdkKUbrsxJBYYpuBLS0HpJw_UwYehM0-AQ1eLtviLnrgh6zSN_IjlxOS8fZ0Z2jK_dwOAulpeIvdGWYoCA0vjIIK2sjGSmb4mrpXA7MaO46IBSt__-J3ApbUus5rN9F6QU3szEkbUrOYWZ0zskaZEjyEnsz_F8FRVWgXpjSvFgsIswY0E5AhXoD9k_iTgnz3joDAkaWWctZ7uubRiwNFV_UJzEiXGU9Hly_8ifW-j1quNH8MbTOGpKVjDV570dlwarBrdmg6dbmuEwdtLT7skNTbHr7lcd9hhJIaVW7ZryeJWPCHZumHmScta0AQgu9G6G-E1qANRncy7vwI-ySR82v7FMQYPSxxeaLc_zzpjuPzq0UHeU3WY3HjL00PrI97OrmLVy8i_uisQsrVwZOdA76CS_XxTNCd_VwgHypxbrQgeFJSijjr7gvla1wfhrDlonce8zsVQkS8g7ApkgFy5tvVed2GvB_GiD6SpPjhJjpmF5FNEpHsFqcNGH28qjTAsgVd6NFursGoHuQFqXBe8IOCwOgqZwjIvp2FcD0dEw0b1q8N9WkQAaIQ');

        $serverPrivateKey = hex2bin('b3469f939fc14aad024486d310389e819c2a82c70d0fb905c8d95a0620df1523');
        $serverPublicKey = Base64Url::decode('BMbTBlMIqkWEQTVW99FMZjTHD6r-5Uhw06nAI3dRmY2Z3deg-_DrxEyBmHSeGDChfRyKvJT4q_QBJBQRtSD1wyU');
        $plaintext = $this->decryptRequest($body, $salt, $uaPublicKey, $authToken, $serverPrivateKey, $serverPublicKey);

        static::assertEquals('Hello! 👋', $plaintext);
    }

    private function decryptRequest(string $ciphertext, string $salt, string $senderPublicKey, string $authSecret, string $receiverPrivateKey, string $receiverPublicKey): string
    {
        $context = 'P-256';
        $context .= chr(0);
        $context .= chr(0);
        $context .= chr(65);
        $context .= $senderPublicKey;
        $context .= chr(0);
        $context .= chr(65);
        $context .= $receiverPublicKey;

        // IKM
        $keyInfo = 'Content-Encoding: auth'.chr(0);
        $ikm = Utils::computeIKM($keyInfo, $authSecret, $senderPublicKey, $receiverPrivateKey, $receiverPublicKey);

        // We compute the PRK
        $prk = hash_hmac('sha256', $ikm, $salt, true);

        $cekInfo = 'Content-Encoding: aesgcm'.chr(0).$context;
        $cek = mb_substr(hash_hmac('sha256', $cekInfo.chr(1), $prk, true), 0, 16, '8bit');

        $nonceInfo = 'Content-Encoding: nonce'.chr(0).$context;
        $nonce = mb_substr(hash_hmac('sha256', $nonceInfo.chr(1), $prk, true), 0, 12, '8bit');

        $C = mb_substr($ciphertext, 0, -16, '8bit');
        $T = mb_substr($ciphertext, -16, null, '8bit');

        $rawData = openssl_decrypt($C, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $T);
        $paddingLength = unpack('n*', mb_substr($rawData, 0, 2, '8bit'))[1];

        return mb_substr($rawData, 2 + $paddingLength, null, '8bit');
    }
}
