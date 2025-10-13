attribute vec3 aPosition;
attribute vec2 aUv0;

uniform mat4 matrix_model;
uniform mat4 matrix_viewProjection;
uniform float uTime;

varying vec2 vUv0;

void main(void)
{
    float time = uTime;
    vec4 pos = matrix_model * vec4(aPosition, 1.0);
    pos.x += sin(time)*cos(pos.y);
    pos.y += sin(time)*cos(pos.x);
    vUv0 = aUv0;
    gl_Position = matrix_viewProjection * pos;
}