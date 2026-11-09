import binascii

target_str = "00020101021133730004BCEL0106ONEPAY7216mch6541c0373ede30314202403271909590513CLOSEWHENDONE53034185405100005803VTE6002LA625305368cc876b4-a4af-4886-81f1-3890453eb5560809Buy Pants6304"
target_crc = 0x40FF

data = target_str.encode('ascii')

def crc16_ccitt_false(data: bytes) -> int:
    crc = 0xFFFF
    for b in data:
        crc ^= (b << 8)
        for _ in range(8):
            if crc & 0x8000:
                crc = ((crc << 1) ^ 0x1021) & 0xFFFF
            else:
                crc = (crc << 1) & 0xFFFF
    return crc

def crc16_xmodem(data: bytes) -> int:
    crc = 0x0000
    for b in data:
        crc ^= (b << 8)
        for _ in range(8):
            if crc & 0x8000:
                crc = ((crc << 1) ^ 0x1021) & 0xFFFF
            else:
                crc = (crc << 1) & 0xFFFF
    return crc

def crc16_kermit(data: bytes) -> int:
    crc = 0x0000
    for b in data:
        crc ^= b
        for _ in range(8):
            if crc & 1:
                crc = (crc >> 1) ^ 0x8408
            else:
                crc = crc >> 1
    return crc

print(f"CCITT-FALSE: {crc16_ccitt_false(data):04X}")
print(f"XMODEM:      {crc16_xmodem(data):04X}")
print(f"KERMIT:      {crc16_kermit(data):04X}")

# Let's search all combinations of poly, init, refin, refout, xorout
for poly in [0x1021, 0x8408, 0x8005, 0xA001]:
    for init in [0x0000, 0xFFFF, 0x1D0F]:
        for xorout in [0x0000, 0xFFFF]:
            # MSB first
            crc = init
            for b in data:
                crc ^= (b << 8)
                for _ in range(8):
                    if crc & 0x8000:
                        crc = ((crc << 1) ^ poly) & 0xFFFF
                    else:
                        crc = (crc << 1) & 0xFFFF
            crc ^= xorout
            if crc == target_crc:
                print(f"MATCH MSB! poly={poly:04X}, init={init:04X}, xorout={xorout:04X}")

            # LSB first
            crc = init
            for b in data:
                crc ^= b
                for _ in range(8):
                    if crc & 1:
                        crc = (crc >> 1) ^ poly
                    else:
                        crc = crc >> 1
            crc ^= xorout
            if crc == target_crc:
                print(f"MATCH LSB! poly={poly:04X}, init={init:04X}, xorout={xorout:04X}")
