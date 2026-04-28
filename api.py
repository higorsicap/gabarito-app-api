from flask import Flask, request, jsonify
from flask_cors import CORS
import cv2
import numpy as np
import base64

app = Flask(__name__)
CORS(app)

# -------------------------------
# Função para ordenar pontos
# -------------------------------
def order_points(pts):
    rect = np.zeros((4, 2), dtype="float32")

    s = pts.sum(axis=1)
    rect[0] = pts[np.argmin(s)]
    rect[2] = pts[np.argmax(s)]

    diff = np.diff(pts, axis=1)
    rect[1] = pts[np.argmin(diff)]
    rect[3] = pts[np.argmax(diff)]

    return rect

# -------------------------------
# Corrigir perspectiva
# -------------------------------
def four_point_transform(image, pts):
    rect = order_points(pts)
    (tl, tr, br, bl) = rect

    widthA = np.linalg.norm(br - bl)
    widthB = np.linalg.norm(tr - tl)
    maxWidth = int(max(widthA, widthB))

    heightA = np.linalg.norm(tr - br)
    heightB = np.linalg.norm(tl - bl)
    maxHeight = int(max(heightA, heightB))

    dst = np.array([
        [0, 0],
        [maxWidth - 1, 0],
        [maxWidth - 1, maxHeight - 1],
        [0, maxHeight - 1]
    ], dtype="float32")

    M = cv2.getPerspectiveTransform(rect, dst)
    warped = cv2.warpPerspective(image, M, (maxWidth, maxHeight))

    return warped

# -------------------------------
# FUNÇÃO CENTRAL DE PROCESSAMENTO
# -------------------------------
def processar_folha(image):
    orig = image.copy()

    # 🔥 1. grayscale
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

    # 🔥 2. melhora contraste (ESSENCIAL)
    gray = cv2.GaussianBlur(gray, (5, 5), 0)
    gray = cv2.equalizeHist(gray)

    # 🔥 3. threshold adaptativo (melhora MUITO detecção de folha)
    thresh = cv2.adaptiveThreshold(
        gray,
        255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY,
        11,
        2
    )

    # 🔥 4. inverte pra detectar borda melhor
    thresh = cv2.bitwise_not(thresh)

    # 🔥 5. fecha buracos
    kernel = np.ones((5, 5), np.uint8)
    thresh = cv2.dilate(thresh, kernel, iterations=2)
    thresh = cv2.erode(thresh, kernel, iterations=1)

    # 🔥 6. contornos
    contours, _ = cv2.findContours(
        thresh,
        cv2.RETR_EXTERNAL,
        cv2.CHAIN_APPROX_SIMPLE
    )

    if not contours:
        return None

    contours = sorted(contours, key=cv2.contourArea, reverse=True)

    screenCnt = None

    # 🔥 7. procura folha (maior retângulo)
    for c in contours[:10]:
        peri = cv2.arcLength(c, True)
        approx = cv2.approxPolyDP(c, 0.02 * peri, True)

        if len(approx) == 4:
            screenCnt = approx
            break

    if screenCnt is None:
        return None

    return four_point_transform(orig, screenCnt.reshape(4, 2).astype("float32"))

# -------------------------------
# LEITURA DE GABARITO
# -------------------------------
def ler_gabarito(image):
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

    thresh = cv2.threshold(
        gray, 0, 255,
        cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU
    )[1]

    contours, _ = cv2.findContours(
        thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE
    )

    bolhas = []

    for c in contours:
        x, y, w, h = cv2.boundingRect(c)

        if 20 < w < 60 and 20 < h < 60:
            ratio = w / float(h)

            if 0.8 <= ratio <= 1.2:
                bolhas.append(c)

    bolhas = sorted(bolhas, key=lambda c: cv2.boundingRect(c)[1])

    respostas = {}
    questao = 1

    for i in range(0, len(bolhas), 4):
        grupo = bolhas[i:i + 4]

        if len(grupo) < 4:
            continue

        grupo = sorted(grupo, key=lambda c: cv2.boundingRect(c)[0])

        preenchimentos = []

        for c in grupo:
            mask = np.zeros(thresh.shape, dtype="uint8")
            cv2.drawContours(mask, [c], -1, 255, -1)

            total = cv2.countNonZero(cv2.bitwise_and(thresh, thresh, mask=mask))
            preenchimentos.append(total)

        idx = np.argmax(preenchimentos)

        alternativas = ['A', 'B', 'C', 'D']
        respostas[f"Q{questao}"] = alternativas[idx]

        questao += 1

    return respostas

# -------------------------------
# ROTA SCAN (corrige imagem)
# -------------------------------
@app.route('/scan', methods=['POST'])
def scan():
    try:
        data = request.json.get('image')

        if not data:
            return jsonify({"error": "Imagem não enviada"}), 400

        image_data = base64.b64decode(data)
        np_arr = np.frombuffer(image_data, np.uint8)
        image = cv2.imdecode(np_arr, cv2.IMREAD_COLOR)

        warped = processar_folha(image)

        if warped is None:
            return jsonify({"error": "Folha não detectada"}), 400

        gray = cv2.cvtColor(warped, cv2.COLOR_BGR2GRAY)
        thresh = cv2.adaptiveThreshold(
            gray, 255,
            cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
            cv2.THRESH_BINARY,
            11, 2
        )

        _, buffer = cv2.imencode('.jpg', thresh)
        encoded = base64.b64encode(buffer).decode('utf-8')

        return jsonify({"image": encoded})

    except Exception as e:
        print(e)
        return jsonify({"error": "Erro interno"}), 500

# -------------------------------
# ROTA GABARITO (OMR)
# -------------------------------
@app.route('/gabarito', methods=['POST'])
def gabarito():
    try:
        data = request.json.get('image')

        if not data:
            return jsonify({"error": "Imagem não enviada"}), 400

        image_data = base64.b64decode(data)
        np_arr = np.frombuffer(image_data, np.uint8)
        image = cv2.imdecode(np_arr, cv2.IMREAD_COLOR)

        warped = processar_folha(image)

        if warped is None:
            return jsonify({"error": "Folha não detectada"}), 400

        respostas = ler_gabarito(warped)

        return jsonify({
            "respostas": respostas
        })

    except Exception as e:
        print(e)
        return jsonify({"error": "Erro ao ler gabarito"}), 500

# -------------------------------
# START
# -------------------------------
if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)