target2_str = "00020101021233980004BCEL0118ONEPAYSUBSCRIPTION0216mch679ae2c0a187d0320SBDMQTGMVMSRUVQUZOLY0406RULE-5051083030620155204729953034185802LA624602102030-06-01050410000820TAITTJVQEKRJZZJUFMIR6304"
target2_crc = 0xDABB

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

c2 = crc16_ccitt_false(target2_str.encode('ascii'))
print(f"Sample 2 CCITT-FALSE: {c2:04X}, Expected: {target2_crc:04X}")

# What if with onepay://qr/?
c2_full = crc16_ccitt_false(("onepay://qr/" + target2_str).encode('ascii'))
print(f"Sample 2 with prefix: {c2_full:04X}")
