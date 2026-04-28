import cv2
import numpy as np

def ler_gabarito(image):

    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)

    # 🔥 binarização mais estável
    thresh = cv2.adaptiveThreshold(
        gray, 255,
        cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
        cv2.THRESH_BINARY_INV,
        31, 5
    )

    QUESTOES = 17
    ALTERNATIVAS = 5

    h, w = thresh.shape

    alternativas = ['A', 'B', 'C', 'D', 'E']

    respostas = {}

    # 🔥 margem de segurança (evita bordas)
    margin_x = int(w * 0.05)
    margin_y = int(h * 0.02)

    usable_w = w - 2 * margin_x
    usable_h = h - 2 * margin_y

    row_h = usable_h // QUESTOES
    col_w = usable_w // ALTERNATIVAS

    for q in range(QUESTOES):

        y1 = margin_y + q * row_h
        y2 = margin_y + (q + 1) * row_h

        scores = []

        for a in range(ALTERNATIVAS):

            x1 = margin_x + a * col_w
            x2 = margin_x + (a + 1) * col_w

            cell = thresh[y1:y2, x1:x2]

            # 🔥 remove ruído pequeno
            kernel = np.ones((2,2), np.uint8)
            cell = cv2.morphologyEx(cell, cv2.MORPH_OPEN, kernel)

            filled = cv2.countNonZero(cell)
            area = cell.shape[0] * cell.shape[1]

            score = filled / float(area + 1e-5)

            scores.append(score)

        # 🔥 evita falso positivo (marca leve / sujeira)
        max_score = max(scores)

        if max_score < 0.15:
            resposta = None  # não marcada
        else:
            idx = int(np.argmax(scores))
            resposta = alternativas[idx]

        respostas[f"Q{q+1}"] = resposta

    return respostas