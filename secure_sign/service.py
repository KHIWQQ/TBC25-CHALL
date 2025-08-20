#!/usr/bin/env python3
#
# TB-CERT AD CTF 2025 :: SecureSign
#

# Documentation imports
from __future__ import annotations
from typing import Tuple, List, Dict, NewType, Union

# Native imports
import base64, json, sqlite3

# External dependencies
# None


# Helper functions
B64Input = NewType('B64Input', (Union[int, str, bytes], List[Union[int, str, bytes]]))
B64Str   = NewType('B64Str', str)

def B64Encode(x: B64Input) -> B64Str:
    """ Encodes various inputs into url-safe base64 strings. """
    if isinstance(x, (int, str, bytes)):
        x = [x]
    y = []
    for i in x:
        if isinstance(i, int):
            y += [i.to_bytes(-(-i.bit_length()//8), 'big')]
        elif isinstance(i, str):
            y += [i.encode()]
        elif isinstance(i, bytes):
            y += [i]
        else:
            raise ValueError()
    z = [base64.urlsafe_b64encode(i).strip(b"===") for i in y]
    return b'.'.join(z).decode()

def B64Decode(bstr: B64Str) -> Union[int, List[int]]:
    """ Decodes url-safe base64 strings into integers. """
    b64s = bstr.encode().split(b'.')
    byts = [base64.urlsafe_b64decode(i + b"===") for i in b64s]
    ints = [int.from_bytes(i, 'big') for i in byts]
    if len(ints) == 1:
        return ints[0]
    return ints

def Inv(x: int, n: int) -> int:
    """ Returns modular inverse of x modulo n using Euclidean algorithm. """
    def Euclid(a: int, b: int) -> int:
        if a == 0 :
            return 0, 1
        s1, t1 = Euclid(b % a, a)
        s = t1 - (b // a) * s1
        t = s1
        return s, t
    return Euclid(x, n)[0] % n



# Classes
class DatabaseInteractionFramework:
    def __init__(self, fname: str) -> None:
        self.db = sqlite3.connect(fname)
        # Check database integrity
        tables  = set(self.db.cursor().execute("SELECT name FROM sqlite_master").fetchall())
        if tables == set():
            self.db.cursor().execute("CREATE TABLE pubkey(uid, pub)")
            self.db.cursor().execute("CREATE TABLE cipsig(uid, hsh, cip, sig)")
        else:
            assert tables == {('cipsig',), ('pubkey',)}

    def ReadPub(self, uid: str) -> B64Str:
        """ Returns public key associated with given UID. """
        result = self.db.cursor().execute("SELECT pub FROM pubkey WHERE uid = ?", [uid]).fetchall()
        assert len(result) < 2
        if result:
            return result[0][0]
        else:
            return ''

    def WritePub(self, uid: str, pub: B64Str) -> str:
        """ Attempts to assign given public key to given UID. """
        try:
            if self.ReadPub(uid):
                raise Exception('DB_ERROR :: UID already exists.')
            self.db.cursor().execute("INSERT INTO pubkey VALUES(?, ?)", [uid, pub])
            self.db.commit()
            assert self.ReadPub(uid) == pub
            return ''
        except Exception as err:
            return err

    def ReadMsg(self, uid: str, hsh: B64Str = '', sig: B64Str = '') -> List[Dict[str, B64Str]]:
        """ Returns matching entries assigned to given UID. """
        sqlstr = "SELECT hsh, cip ,sig FROM cipsig WHERE uid = ?"
        sqldat = [uid]
        if hsh:
            sqlstr += " AND hsh = ?"
            sqldat += [hsh]
        if sig:
            sqlstr += " AND sig = ?"
            sqldat += [sig]
        result = self.db.cursor().execute(sqlstr, sqldat).fetchall()
        return [{'hsh': i[0], 'cip': i[1], 'sig': i[2]} for i in result]

    def WriteMsg(self, uid: str, hsh: B64Str, cip: B64Str, sig: B64Str) -> str:
        """ Attempts to assign given data to given UID. """
        try:
            res = self.ReadMsg(uid, hsh = hsh)
            if res:
                if cip == res[0]['cip']:
                    raise Exception('DB_ERROR :: Entry already exists.')
                else:
                    raise Exception('DB_ERROR :: Collision detected with {}.'.format(res[0]['cip']))
            else:
                res = self.ReadMsg(uid, sig = sig)
                if res:
                    raise Exception('DB_ERROR :: Collision detected with {}.'.format(res[0]['cip']))
            self.db.cursor().execute("INSERT INTO cipsig VALUES(?, ?, ?, ?)", [uid, hsh, cip, sig])
            self.db.commit()
            assert self.ReadMsg(uid, hsh = hsh) == [{'hsh': hsh, 'cip': cip, 'sig': sig}]
            return ''
        except Exception as err:
            return err
        
    def Close(self) -> None:
        """ Closes connection to the database. """
        self.db.close()


class ServiceInteractionFramework:
    def __init__(self, domain: Dict[str, Dict[str, int]]) -> None:
        self.rsa = domain['rsa']
        self.dsa = domain['dsa']
        self.dif = DatabaseInteractionFramework('storage.db')
        
    def __Encrypt(self, msg: B64Str) -> B64Str:
        """ Encrypts given message using service RSA public key. """
        return B64Encode(pow(B64Decode(msg), self.rsa['e'], self.rsa['n']))
    
    def __Decrypt(self, cip: B64Str) -> B64Str:
        """ Decrypts given ciphertext using service RSA private key. """
        return B64Encode(pow(B64Decode(cip), self.rsa['d'], self.rsa['n']))
    
    def __Hash(self, msg: B64Str) -> B64Str:
        """ Returns digest of given message using RSA hashing. """
        return B64Encode(B64Decode(self.__Encrypt(msg)) % self.dsa['q'])
        
    def __Verify(self, msg: B64Str, pub: B64Str, sig: B64Str) -> bool:
        """ Attempts to verify a signature sig of message msg as signed by public key pub. """
        try:
            r, s = B64Decode(sig)
            u = Inv(s, self.dsa['q'])
            v = pow(self.dsa['g'], B64Decode(self.__Hash(msg)) * u, self.dsa['p'])
            w = pow(B64Decode(pub), r * u, self.dsa['p'])
            assert ((v * w) % self.dsa['p']) % self.dsa['q'] == r
            return True
        except:
            return False
    
    def Get(self, uid: str, hsh: B64Str = '', sig: B64Str = '') -> Dict[B64Str, Union[B64Str, List[Dict[B64Str, B64Str]]]]:
        """ Retrieves stored data from database given UID, hash (optional), and signature (optional). """
        pubkey = self.dif.ReadPub(uid)
        cipsig = self.dif.ReadMsg(uid, hsh = hsh, sig = sig)
        return {
            "pub" : pubkey,
            "msg" : [{
                "hsh" : i["hsh"],
                "sig" : i["sig"]
            } for i in cipsig]
        }
    
    def Register(self, uid: str, pub: B64Str) -> str:
        """ Attempts to assign a public key to given UID within the database. """
        try:
            assert uid
            assert pub
            err = self.dif.WritePub(uid, pub)
            return err
        except Exception as err:
            return err
    
    def Write(self, uid: str, msg: B64Str, sig: B64Str) -> str:
        """ Attempts to assign a signed message to given UID within the database. """
        try:
            assert msg
            pub = self.dif.ReadPub(uid)
            assert self.__Verify(msg, pub, sig)
            err = self.dif.WriteMsg(uid, self.__Hash(msg), self.__Encrypt(msg), sig)
            return err
        except Exception as err:
            return err
        
    def Decrypt(self, uid: str, hsh: B64Str, sig: B64Str) -> Tuple[str, B64Str]:
        """ Attempts to verify distinct signature of stored message and decrypts if successful. """
        try:
            pub = self.dif.ReadPub(uid)
            cip = self.dif.ReadMsg(uid, hsh = hsh)
            assert cip[0]['sig'] != sig
            msg = self.__Decrypt(cip[0]['cip'])
            assert self.__Verify(msg, pub, sig)
            return '', msg
        except Exception as err:
            return err, ''


# Service setup
P, k = B64Decode('s7YtdT-WSAWqfq3PhKYBl1bgWPcycxF7crGe-Ksq7DSVwZTpq4EmryXp1jqlp7kGTMsOrv35E2iyO4OjUari63gMgm_HFf7ra9qHLZEq2mF6KNbzVX-yUiG_mHkFzBJTE4TmziN8OkVbqzUBzBJhDCm-mmpWCq5aeHv-cbI57AE.AgA')
p, q = B64Decode('oFH5UJhNXdw0z9LuBc5kUyhW8kzznUZ1WsWV3QV2rBc9royzvXFmbutuoTU9eJ0q1viO7wKxoyz1CaxZNSSCHligAzNyLRhP-D6zNybsJaEAKHLz_h3_gmEOKPwLJXCtx6p4g717Uef2YU2iGEvHk6TXKhXZHWgXJIea3aaRyhU.m1p6RVmlajxrvz-jedil3JMJPfZYa_973At6IlM_wf1Rs7xxH7cHA8GCHJ5t6PogV7ejOBsu9xLGMsUdXbD2jZj-g-v1Ku2ROAFNsQtDZCilyKrbBigba1q1E6gItNo8Ki67Q2RyK2baHf9BrmBeQQMeJNz8_AAFmylx8PHRguE')
DOMAIN = {
    'dsa' : {
        'p' : P,
        'k' : k,
        'q' : (P - 1) // (2 * k),
        'g' : pow(2, 2 * k, P)
    },
    'rsa' : {
        'n' : p * q,
        'e' : 0x10001,
        'd' : Inv(0x10001, (p - 1) * (q - 1))
    }
}
SIF = ServiceInteractionFramework(DOMAIN)


# Main loop
if __name__ == "__main__":
    HDR = r"""|
    |
    |      /\
    |     /  \
    |    /    \  ____  ___  _  _  ____  ____ 
    |   /   /  \(  __)/ __)/ )( \(  _ \(  __)
    |  (   (    )) _)( (__ ) \/ ( )   / ) _) 
    |   \   \  /(____)\___)\____/(__\_)(____)
    |    \   \/
    |    /\   \  __  ___  __ _    
    |   /  \   \(  )/ __)(  ( \      
    |  (    )   ))(( (_ \/    /  
    |   \  /   /(__)\___/\_)__)v1.27
    |    \    /
    |     \  /
    |      \/
    |
    |  [~] Our DOMAIN parameters ::
    |    DSA = {}
    |    RSA = {}
    |""".format(B64Encode([DOMAIN['dsa']['p'], DOMAIN['dsa']['k']]), B64Encode([DOMAIN['rsa']['n'], DOMAIN['rsa']['e']]))
    print(HDR)

    TUI = "|\n|  Menu:\n|    [G]et\n|    [R]egister\n|    [W]rite\n|    [D]ecrypt\n|    [Q]uit\n|"

    while True:
        try:
            
            print(TUI)
            choice = input('|  > ').lower()
            
            # [Q]uit
            if choice == 'q':
                print('|\n|  [~] Goodbye ~ !\n|')
                break
                
            # [G]et
            elif choice == 'g':
                print("|\n|  [?] {'uid': str}")
                userInput = json.loads(input('|  > (JSON) '))
                
                res = SIF.Get(userInput['uid'])
                if res['pub']:
                    print('|\n|  [~] Success: {}'.format(json.dumps(res)))
                else:
                    print('|\n|  [!] Unknown user.')
            
            # [R]egister
            elif choice == 'r':
                print("|\n|  [?] {'uid': str, 'pub': B64Str}")
                userInput = json.loads(input('|  > (JSON) '))
                
                err = SIF.Register(userInput['uid'], userInput['pub'])
                if err:
                    print('|\n|  [!] {}'.format(err))
                else:
                    print('|\n|  [~] Success ~ !')
            
            # [W]rite
            elif choice == 'w':
                print("|\n|  [?] {'uid': str, 'msg': B64Str, 'sig': B64Str}")
                userInput = json.loads(input('|  > (JSON) '))
                
                err = SIF.Write(userInput['uid'], userInput['msg'], userInput['sig'])
                if err:
                    print('|\n|  [!] {}'.format(err))
                else:
                    print('|\n|  [~] Success ~ !')
            
            # [D]ecrypt
            elif choice == 'd':
                print("|\n|  [?] {'uid': str, 'hsh': B64Str, 'sig': B64Str}")
                userInput = json.loads(input('|  > (JSON) '))
                
                err, msg = SIF.Decrypt(userInput['uid'], userInput['hsh'], userInput['sig'])
                if err:
                    print('|\n|  [!] {}'.format(err))
                else:
                    print('|\n|  [~] Success: {}'.format(msg))
                
            else:
                print('|\n|  [!] Invalid choice.')
                
        except KeyboardInterrupt:
            print('\n|\n|  [~] Goodbye ~ !\n|')
            break
            
        except Exception as err:
            print('|\n|  [!] {}'.format(err))

    SIF.dif.Close()
