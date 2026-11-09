def parse_emvco(payload: str) -> dict:
    # Strip onepay://qr/ or other prefix if present
    prefix = ""
    if "000201" in payload:
        idx = payload.index("000201")
        prefix = payload[:idx]
        payload = payload[idx:]

    tags = {}
    i = 0
    while i < len(payload):
        if i + 4 > len(payload):
            break
        tag = payload[i:i+2]
        try:
            length = int(payload[i+2:i+4])
        except ValueError:
            break
        val = payload[i+4:i+4+length]
        tags[tag] = val
        i += 4 + length
        if tag == "63":
            break
    return prefix, tags

def build_emvco(tags: dict, prefix: str = "") -> str:
    # Build in standard EMVCo tag order
    ordered_tags = sorted(tags.keys(), key=lambda t: (int(t) if t.isdigit() else 999))
    p = ""
    for t in ordered_tags:
        if t == "63": continue
        val = tags[t]
        p += f"{int(t):02d}{len(val):02d}{val}"

    # Add 6304 and CRC
    to_crc = p + "6304"
    crc = crc16_ccitt(to_crc.encode('ascii'))
    crc_hex = f"{crc:04X}"
    return prefix + to_crc + crc_hex

def crc16_ccitt(data: bytes) -> int:
    crc = 0xFFFF
    for b in data:
        crc ^= (b << 8)
        for _ in range(8):
            if crc & 0x8000:
                crc = ((crc << 1) ^ 0x1021) & 0xFFFF
            else:
                crc = (crc << 1) & 0xFFFF
    return crc

# Test on Sample 2
sample2 = "onepay://qr/00020101021233980004BCEL0118ONEPAYSUBSCRIPTION0216mch679ae2c0a187d0320SBDMQTGMVMSRUVQUZOLY0406RULE-5051083030620155204729953034185802LA624602102030-06-01050410000820TAITTJVQEKRJZZJUFMIR6304DABB"
prefix, tags = parse_emvco(sample2)
print("Parsed tags from Sample 2:", list(tags.keys()))
print("Tag 33:", tags.get("33"))
print("Prefix:", prefix)

# Now inject new amount 85,000 Kip
tags["01"] = "12"
tags["54"] = "85000"
new_qr = build_emvco(tags, prefix)
print("\nNew Dynamic QR with injected amount:")
print(new_qr)
p2, new_tags = parse_emvco(new_qr)
print("Verified Tag 54 in new QR:", new_tags.get("54"))
print("Verified CRC in new QR:", new_tags.get("63"))
