attribute vec3 aPosition;
uniform mat4 matrix_model;
uniform mat4 matrix_viewProjection;
varying vec2 vUv;

void main(void) {
    // Passe la position transformée à l'étape de rasterisation
    gl_Position = matrix_viewProjection * matrix_model * vec4(aPosition, 1.0);
    
    // Utilise les coordonnées x et z comme UVs pour l'effet arc-en-ciel
    vUv = aPosition.xz;
}

/*FRAGMENT_SHADER*/

precision mediump float;
uniform float uTime;
varying vec2 vUv;

void main(void) {
    // Crée un effet de déplacement des couleurs avec le temps
    float colorIndex = mod(vUv.x + uTime * 0.2, 1.0) * 7.0; // Multiplier par 7 pour créer 7 bandes

    vec3 color = vec3(0.0);
    if(colorIndex < 1.0) {
        color = vec3(1.0, 0.0, 0.0); // Rouge
    } else if(colorIndex < 2.0) {
        color = vec3(1.0, 0.5, 0.0); // Orange
    } else if(colorIndex < 3.0) {
        color = vec3(1.0, 1.0, 0.0); // Jaune
    } else if(colorIndex < 4.0) {
        color = vec3(0.0, 1.0, 0.0); // Vert
    } else if(colorIndex < 5.0) {
        color = vec3(0.0, 0.0, 1.0); // Bleu
    } else if(colorIndex < 6.0) {
        color = vec3(0.29, 0.0, 0.51); // Indigo
    } else {
        color = vec3(0.56, 0.0, 1.0); // Violet
    }

    gl_FragColor = vec4(color, 1.0);
}
