from PyPDF2 import PdfReader
from pathlib import Path

pdf_path = Path(r"C:\Users\User\Desktop\Degree\5\BACKEND\Campus Lost And Found\context\ITT626 CASE STUDY.pdf")
reader = PdfReader(str(pdf_path))
out_path = pdf_path.with_suffix(".txt")

parts = []
for i, page in enumerate(reader.pages, start=1):
    text = page.extract_text() or ""
    parts.append("\n=== Page {} ===\n{}".format(i, text))

out_path.write_text("\n".join(parts), encoding="utf-8")
print(out_path)
