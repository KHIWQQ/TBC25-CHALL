from auth import register, login, get_keypair, init_admins, generate_token, decrypt_token,NONCE,send_msg,AES_KEY,list_all_public_keys
from curve_op import *
import os
import json
from hashlib import sha256
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad, unpad
import base64

KEYS_FILE = 'keys.log'
MSG_FILE = 'messages.log'


class current_user:
	def __init__(self, username, token):
		self.user=bytes.fromhex(username)
		self.user_token=token
	def number_of_messages(self):
		if not os.path.exists(MSG_FILE):
			print("[!] No messages found.")
		count=0
		try:
			with open(MSG_FILE) as f:
				for line in f:
					msg = json.loads(line)
					if msg["from"] == self.user or msg["to"] == self.user:
						count+=1
		except:
			count=0
		print(f"[+] Your User '{self.user}' Have {count} Messages".format(self=self))


def prompt(msg):
	print(msg, end="", flush=True)
	return input()

def log_message(sender, recipient, ciphertext_hex):
	with open(MSG_FILE, 'a') as f:
		f.write(json.dumps({
			"from": sender,
			"to": recipient,
			"data": ciphertext_hex
		}) + "\n")

def write_note_to_app_admin(filename,data):
	try:
		FORBIDDEN_NAMES = {
			"aes_key.bin", "auth.py", "curve_op.py", "keys.log", "messages.log",
			"nonce.bin","server.py", "users.json","Flag4.txt","Flag3.txt"
		}
		base_dir="."
		data=base64.b64decode(data.encode())
		filename = filename.strip()
		if os.path.isabs(filename):
			print("Not allowed.")
		full_path = os.path.abspath(os.path.join(base_dir, filename))
		base_path = os.path.abspath(base_dir)
		base_filename = os.path.basename(full_path)
		if base_filename in FORBIDDEN_NAMES or full_path.endswith(".py"):
			print(f"Filename '{filename}' is not allowed.")
		filepath = os.path.join(".", filename)
		os.makedirs(os.path.dirname(full_path), exist_ok=True)
		with open(filepath, "wb") as f:
			f.write(data)
		print(f"[+] Note written successfully!")
	except Exception as e:
		print(f"[!] Error writing note: {e}")

def list_messages(username):
	if not os.path.exists(MSG_FILE):
		print("[!] No messages found.")
		return
	messages = []
	with open(MSG_FILE) as f:
		for line in f:
			msg = json.loads(line)
			if msg["from"] == username or msg["to"] == username:
				messages.append(msg)
				print(msg)
		if len(messages) == 0:
			print("[!] No messages found.")

def derive_key(shared_point):
	shared_secret = sha256(str(shared_point.x).encode()).digest()
	return shared_secret[:16], shared_secret[:12] 

def ecc_mul(P, priv):
	return double_and_add(P, priv, C)

def get_all_keys():
	keys = {}
	if os.path.exists(KEYS_FILE):
		with open(KEYS_FILE) as f:
			for line in f:
				username, priv, x, y = line.strip().split(":")
				keys[username] = (int(priv), Point(int(x), int(y)))
	return keys



def user_interface(username, priv, pub,token):
	while True:
		print("\n[1] Set custom public key")
		print("[2] Send message to admin_1 or admin_2")
		print("[3] View messages for a user")
		print("[4] List PKs")
		print("[5] Current user messsages")
		print("[6] Write a note to app admin")
		choice = prompt("> ").strip()
		cur_user=current_user(username,token)
		if choice == "1":
			x = int(prompt("x = ").strip())
			y = int(prompt("y = ").strip())
			pub = Point(x, y)
			print(f"[+] Your public key is now: ({x}, {y})")
			print(pub)

		elif choice == "2":
			print(send_msg(username, priv, pub))

		elif choice == "3":
			uname = prompt("Username to view: ").strip().encode().hex()
			list_messages(uname)

		elif choice == "4":
			list_all_public_keys()
		
		elif choice == "5":
			cur_user.number_of_messages()
			list_messages(username)
		
		elif choice == "6":
			title = prompt("Note Title: ").strip()
			content = prompt("content in base64: ").strip()
			write_note_to_app_admin(title,content)
		else:
			print("[!] Invalid option.")



def main():
	while True:
		print("Welcome to the Secure Messaging Service")
		print("Do you want to [1] Register or [2] Login?")
		choice = prompt("> ").strip()

		if choice == "1":
			username = input(("Choose a username: ").strip()).encode()
			success, result = register(username,NONCE,AES_KEY)
			if success:
				print(f"[+] Registered successfully.")
				print(f"[+] Your token:\n{result}")
			else:
				print(f"[!] Registration failed: {result}")
			

		elif choice == "2":
			token = prompt("Enter your token: ").strip()
			success, username, msg = login(token,NONCE,AES_KEY)
			if not success:
				print(f"[!] Login failed: {msg}")
				return
			print(f"[+] Logged in as {bytes.fromhex(username)}")

			priv, pub = get_keypair(username)
			print(f"[+] Your ECC private key: {priv}")
			print(f"[+] Your ECC public key: ({pub.x}, {pub.y})")

			user_interface(username, priv, pub,token)

		else:
			print("Invalid choice.")
			return

if __name__ == "__main__":
	main()

