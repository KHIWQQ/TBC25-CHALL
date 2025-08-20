import json
import os
from hashlib import sha256
from Crypto.Cipher import AES
from Crypto.Random import get_random_bytes
from Crypto.Util.Padding import pad, unpad
from Crypto.Util import Counter
from Crypto.Cipher import AES
from Crypto.Random import get_random_bytes
from curve_op import *

USERS_FILE = 'users.json'
KEYS_FILE = 'keys.log'
MSG_FILE = 'messages.log'


def load_bin_file(path, expected_len):
    if not os.path.exists(path):
        raise FileNotFoundError(f"[!] Missing file: {path}")
    with open(path, "rb") as f:
        data = f.read()
        if len(data) != expected_len:
            raise ValueError(f"[!] Invalid length for {path}: expected {expected_len}, got {len(data)}")
        return data

AES_KEY = load_bin_file("aes_key.bin", 16)
NONCE = load_bin_file("nonce.bin", 12)

def load_users():
    if not os.path.exists(USERS_FILE):
        return {}
    with open(USERS_FILE, 'r') as f:
        return json.load(f)

def save_users(users):
    with open(USERS_FILE, 'w') as f:
        json.dump(users, f)
def prompt(msg):
	print(msg, end="", flush=True)
	return input()


def generate_token(username,NONCE,AES_KEY):
    header=b'ctf_ae'
    cipher = AES.new(AES_KEY, AES.MODE_GCM, nonce=NONCE)
    encrypted = cipher.update(header)
    
    token, tag = cipher.encrypt_and_digest(username.encode())
    return token.hex() + ':' + tag.hex()

def decrypt_token(token,NONCE,AES_KEY):
    token_bytes, tag_bytes = bytes.fromhex(token.split(':')[0]), bytes.fromhex(token.split(':')[1])
    header=b'ctf_ae'
    cipher = AES.new(AES_KEY, AES.MODE_GCM, nonce=NONCE)
    encrypted = cipher.update(header)
    return cipher.decrypt_and_verify(token_bytes, tag_bytes).decode()

def register(username,NONCE,AES_KEY, ad = False):
    users = load_users()
    username=username.hex()
    if username in users and ad ==False:
        token = generate_token(username,NONCE,AES_KEY)
        return False, f"User already exists. here is the ct {token.split(':')[0]}"
    users[username] = {"registered": True}
    save_users(users)
    token = generate_token(username,NONCE,AES_KEY)
    return True, token

def login(token,NONCE,AES_KEY):
    try:
        username = decrypt_token(token,NONCE,AES_KEY)
        print(username)
        users = load_users()
        if username not in users:
            return False, None, "Invalid user."

        key_exists = False
        if os.path.exists(KEYS_FILE):
            with open(KEYS_FILE, 'r') as f:
                for line in f:
                    if line.startswith(username + ":"):
                        key_exists = True
                        break

        if not key_exists:
            priv = int.from_bytes(os.urandom(32), 'big')
            pub = double_and_add(P_256.G, priv, P_256)
            with open(KEYS_FILE, 'a') as f:
                f.write(f"{username}:{priv}:{pub.x}:{pub.y}\n")

        return True, username, "Login successful."
    except Exception as e:
        return False, None, f"Token error: {e}"

def get_keypair(username):
    if os.path.exists(KEYS_FILE):
        with open(KEYS_FILE, 'r') as f:
            for line in f:
                if line.startswith(username + ":"):
                    _, priv, x, y = line.strip().split(":")
                    return int(priv), Point(int(x), int(y))
    return None, None

def init_admins():
    for admin in [b"admin_1", b"admin_2"]:
        success, token = register(admin,NONCE,AES_KEY,ad = True )
        if success:
            print(login(token,NONCE,AES_KEY))
            print(f"[+] Admin registered: {admin}")
            print(f"[+] Token: {token}")
        else:
            print(f"[!] Admin {admin} already registered")
    admin_handshake()

def log_message(sender, recipient, ciphertext_hex):
	with open(MSG_FILE, 'a') as f:
		f.write(json.dumps({
			"from": sender,
			"to": recipient,
			"data": ciphertext_hex
		}) + "\n")

def list_messages(username):
	if not os.path.exists(MSG_FILE):
		print("[!] No messages found.")
		return
	with open(MSG_FILE) as f:
		for line in f:
			msg = json.loads(line)
			if msg["from"] == username or msg["to"] == username:
				print(msg)

def derive_key(shared_point):
	shared_secret = sha256(str(shared_point.x).encode()).digest()
	return shared_secret[:16] , shared_secret[-16:] 

def ecc_mul(P, priv, C=P_256):
	return double_and_add(P, priv, C)

def get_all_keys():
	keys = {}
	if os.path.exists(KEYS_FILE):
		with open(KEYS_FILE) as f:
			for line in f:
				username, priv, x, y = line.strip().split(":")
				keys[username] = (int(priv), Point(int(x), int(y)))
	return keys


def get_all_usernames():
    if not os.path.exists(KEYS_FILE):
        return []
    usernames = []
    with open(KEYS_FILE, 'r') as f:
        for line in f:
            parts = line.strip().split(":")
            if len(parts) == 4:
                usernames.append(parts[0])
    return usernames


def send_msg(username, priv, pub):
    to = prompt("Recipient : ").strip().encode().hex()
    
    if to not in get_all_usernames() :
        return "[!] Invalid recipient." 

    keys = get_all_keys()
    if to not in keys:
        return "[!] key not found."

    target_priv, target_pub = keys[to]


    shared = ecc_mul(pub, target_priv)
    print(f"[DEBUG] shared = ({shared.x}, {shared.y})")
    
    key, iv = derive_key(shared)

    print("\n[+] Past decrypted messages between you and", bytes.fromhex(to))
    if os.path.exists(MSG_FILE):
        with open(MSG_FILE) as f:
            for line in f:
                try:
                    msg = json.loads(line)
                    if (msg["from"] == username and msg["to"] == to) or (msg["from"] == to and msg["to"] == username):
                        data_hex = msg["data"]
                        ct_hex, tag_hex = data_hex.split(":")
                        ct = bytes.fromhex(ct_hex)
                        tag = bytes.fromhex(tag_hex)
                        cipher = AES.new(key, AES.MODE_GCM, nonce=iv)
                        pt = cipher.decrypt_and_verify(ct, tag)
                        print(f"{bytes.fromhex(msg['from'])}: {pt}")
                except Exception:
                    continue   

    
    msg = prompt("Message to send: ").encode()
    cipher = AES.new(key, AES.MODE_GCM, nonce=iv)
    ct, tag = cipher.encrypt_and_digest(msg)
    full_ct = ct.hex() + ":" + tag.hex()
    log_message(username, to, full_ct)
    return f"[+] Message sent to {to}"

	
def list_all_public_keys():
    if not os.path.exists(KEYS_FILE):
        print("[!] No keys file found.")
        return

    with open(KEYS_FILE, 'r') as f:
        print(f"{'Username':<15} {'x':<40} {'y'}")
        print("-" * 100)
        for line in f:
            parts = line.strip().split(":")
            if len(parts) != 4:
                continue
            username, _, x, y = parts
            print(f"{username:<15} {x:<40} {y}")

def admin_handshake():
    keys = get_all_keys()
    a1_priv, a1_pub = keys['61646d696e5f31']
    a2_priv, a2_pub = keys['61646d696e5f32']


    shared = ecc_mul(a2_pub, a1_priv)
    key ,iv = derive_key(shared)
    cipher = AES.new(key, AES.MODE_GCM, nonce=iv)
    pt = b"FLAG{random_hex_here}"
    ct, tag = cipher.encrypt_and_digest(pt)

    full_ct = ct.hex() +":"+ tag.hex()
    log_message("admin_1", "admin_2", full_ct)
    print("[+] Admin handshake complete. Message sent from admin_1 to admin_2.")

if __name__ == "__main__":
    init_admins()

