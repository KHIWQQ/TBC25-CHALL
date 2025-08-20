from os import urandom
from collections import namedtuple
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad
from Crypto.Util.number import inverse
from hashlib import sha256





Point = namedtuple("Point", "x y")
Curve = namedtuple("Curve", "p a b G")

O = "Origin"


p = 0x7fffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffed
a = 19298681539552699237261830834781317975544997444273427339909597334573241639236
b = 55751746669818908907645289078257140818241103727901012315294400837956729358436
G = Point(19298681539552699237261830834781317975544997444273427339909597334652188435546 , 14781619447589544791020593568409986887264606134616475288964881837755586237401 )
P_256 = Curve(p, a, b, G)


def point_inverse(P, C):
    if P == O:
        return P
    return Point(P.x, -P.y % C.p)


def point_addition(P, Q, C):
    if P == O:
        return Q
    elif Q == O:
        return P
    elif Q == point_inverse(P, C):
        return O
    else:
        if P == Q:
            lam = (3 * P.x**2 + C.a) * inverse(2 * P.y, C.p)
            lam %= C.p
        else:
            lam = (Q.y - P.y) * inverse((Q.x - P.x), C.p)
            lam %= p
    Rx = (lam**2 - P.x - Q.x) % C.p
    Ry = (lam * (P.x - Rx) - P.y) % C.p
    R = Point(Rx, Ry)
    return R


def double_and_add(P, n, C):
    Q = P
    R = O
    while n > 0:
        if n % 2 == 1:
            R = point_addition(R, Q, C)
        Q = point_addition(Q, Q, C)
        n = n // 2
    return R


