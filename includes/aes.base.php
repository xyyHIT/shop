<?php
/*
* 与java互通的AES-128-ECB加密模式，key需要为16位。
* author:newrain
* date:20161202
*/
class Security {
	//php mcrypt加密算法
	public static function encrypt($input, $key) {
		$size = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
		$input = Security::pkcs5_pad($input, $size);
		$td = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
		$iv = mcrypt_create_iv (mcrypt_enc_get_iv_size($td), MCRYPT_RAND);
		mcrypt_generic_init($td, $key, $iv);
		$data = mcrypt_generic($td, $input);
		mcrypt_generic_deinit($td);
		mcrypt_module_close($td);
		$data = base64_encode($data);
		return $data;
	}
	//模拟java  PKCS5Padding  对称加密  只单一支持（拍卖）
	private static function pkcs5_pad ($text, $blocksize) {
		$pad = $blocksize - (strlen($text) % $blocksize);
		return $text . str_repeat(chr($pad), $pad);
	}
	//php mcrypt解密算法
	public static function decrypt($sStr, $sKey) {
		$decrypted= mcrypt_decrypt(
			MCRYPT_RIJNDAEL_128,
			$sKey,
			base64_decode($sStr),
			MCRYPT_MODE_ECB
		);
		$dec_s = strlen($decrypted);
		$padding = ord($decrypted[$dec_s-1]);
		$decrypted = substr($decrypted, 0, -$padding);
		return $decrypted;
	}	
}