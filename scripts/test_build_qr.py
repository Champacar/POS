def emvco_tlv(tag: str, val: str) -> str:
    return f"{int(tag):02d}{len(val):02d}{val}"

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

def build_qr(account: str, amount: float, name: str = "Xaipunya VIRACHACK", bill_no: str = "INV-001"):
    # Format phone or account
    clean_acc = account.strip().replace(" ", "").replace("-", "")
    is_phone = clean_acc.startswith("020") or clean_acc.startswith("20") or clean_acc.startswith("+856")
    if clean_acc.startswith("+856"):
        intl_phone = "00856" + clean_acc[4:]
    elif clean_acc.startswith("020"):
        intl_phone = "0085620" + clean_acc[3:]
    elif clean_acc.startswith("20"):
        intl_phone = "0085620" + clean_acc[2:]
    else:
        intl_phone = clean_acc

    # Tag 00
    p = emvco_tlv("00", "01")
    # Tag 01: Dynamic
    p += emvco_tlv("01", "12")

    # Tag 26: Domestic Merchant Account Information (LA.BCEL / Bank Scheme)
    sub26 = emvco_tlv("00", "LA.BCEL")
    sub26 += emvco_tlv("01", clean_acc)
    p += emvco_tlv("26", sub26)

    # Tag 29: LAPNet Interoperable Scheme
    sub29 = emvco_tlv("00", "com.lapnet")
    sub29 += emvco_tlv("01", intl_phone if is_phone else clean_acc)
    p += emvco_tlv("29", sub29)

    # Tag 33: BCEL OnePay
    sub33 = emvco_tlv("00", "BCEL")
    sub33 += emvco_tlv("01", "ONEPAY")
    sub33 += emvco_tlv("02", clean_acc)
    p += emvco_tlv("33", sub33)

    # Tag 52: Pharmacy MCC
    p += emvco_tlv("52", "5912")

    # Tag 53: LAK (418)
    p += emvco_tlv("53", "418")

    # Tag 54: Amount
    p += emvco_tlv("54", f"{amount:.0f}")

    # Tag 58: Country
    p += emvco_tlv("58", "LA")

    # Tag 59: Merchant / Payee Name (max 25 chars, alphanumeric)
    clean_name = "".join(c for c in name if c.isalnum() or c == " ")[:25].strip()
    if not clean_name: clean_name = "PHARMACY"
    p += emvco_tlv("59", clean_name)

    # Tag 60: City
    p += emvco_tlv("60", "VIENTIANE")

    # Tag 62: Additional Data (Bill Reference)
    if bill_no:
        sub62 = emvco_tlv("01", bill_no[:25])
        p += emvco_tlv("62", sub62)

    # Tag 63: CRC16
    to_crc = p + "6304"
    crc_val = crc16_ccitt(to_crc.encode('ascii'))
    crc_hex = f"{crc_val:04X}"
    full_payload = to_crc + crc_hex

    return full_payload

payload = build_qr("02059935897", 50000)
print("Payload:")
print(payload)
print("\nLength:", len(payload))
print("Valid CRC check:", crc16_ccitt(payload[:-4].encode('ascii')) == int(payload[-4:], 16))
