attribute vec3 vertex_position;
attribute vec3 vertex_normal;
attribute vec2 aUv0;

uniform mat4 matrix_model;
uniform mat4 matrix_viewProjection;
uniform mat3 matrix_normal;

varying vec2 vUv0;
varying vec3 vNormal;
varying vec3 vNormalW;
varying vec3 vPositionW;

uniform float uTime;

#define PI  3.141592653589793238462643383279
#define TAU 6.283185307179586476925286766559
#define aPosition = vertex_position
float getWave( vec2 uv) {
    vec2 uvsCentered = uv * 2.0 - 1.0;
    float radialDistance = length(uvsCentered);
    float speed = 0.2;
    float wave = cos((radialDistance - uTime * speed) * TAU * 5.0);
    wave *= 1.0 - radialDistance;
    return wave;
}

void main(void) {

    float amplitude = 0.04;
    float wave = getWave(aUv0);
    vec3 vertex = vertex_position;
    vertex.z = wave * amplitude;

    // gl_Position represents vertex position in clip space, range -1.0 to 1.0
    // clip space is in homogeneous coordinates. (x, y, z, w).
    // normalized device coordinates (x/w, y/w, z/w) contain the true values of x, y, z
    vec4 posW = matrix_model * vec4(vertex, 1.0);
    gl_Position = matrix_viewProjection * posW;
    vec4 ndc = gl_Position / gl_Position.w; 

    vUv0 = aUv0;
    vNormal = vertex_normal;
    vNormalW = normalize(matrix_normal * vertex_normal);
    vPositionW = posW.xyz;
}


