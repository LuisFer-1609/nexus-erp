"""
Nexus, mascota 3D (robot asistente) para NexusERP.

Uso (headless):
    blender -b --factory-startup --python branding/nexus-3d/nexus_robot.py -- [samples] [modo]

    modo = preview (por defecto): .blend, .glb y render con fondo cian.
    modo = sprites: capas con fondo transparente y cámara de frente en
        branding/nexus-3d/sprites/ (cabeza sin ojos, cuerpo, avatar con ojos) y layout.json
        con la posición de los ojos. Los convierte build_assets.py.

Genera en branding/nexus-3d/:
    nexus-robot.blend  -> abrir en Blender (File > Open)
    nexus-robot.glb    -> para web / Three.js / visores 3D
    nexus-robot.png    -> render de vista previa
"""

import math
import os
import sys

import bpy

try:
    HERE = os.path.dirname(os.path.abspath(__file__))
except NameError:  # ejecutado con exec() dentro de Blender
    HERE = "/home/castaneda/Documentos/nexus-erp/branding/nexus-3d"
BLEND = os.path.join(HERE, "nexus-robot.blend")
GLB = os.path.join(HERE, "nexus-robot.glb")
PNG = os.path.join(HERE, "nexus-robot.png")

ARGV = sys.argv[sys.argv.index("--") + 1 :] if "--" in sys.argv else []
SAMPLES = int(ARGV[0]) if ARGV else 128
MODE = ARGV[1] if len(ARGV) > 1 else "preview"
SPRITES = MODE == "sprites"
SPRITE_DIR = os.path.join(HERE, "sprites")
SPRITE_SIZE = 768

WHITE = (0.93, 0.95, 0.97, 1.0)
TEAL = (0.16, 0.74, 0.76, 1.0)
DARK = (0.04, 0.07, 0.10, 1.0)
VISOR = (0.02, 0.22, 0.95, 1.0)
GLOW_EYE = (0.75, 0.93, 1.0, 1.0)
BACKDROP = (0.0, 0.93, 1.0, 1.0)
WORLD_LIGHT = (0.22, 0.24, 0.27, 1.0)


def clear_scene() -> None:
    bpy.ops.wm.read_factory_settings(use_empty=True)


def material(name, color, roughness=0.35, metallic=0.0, emit=None, strength=0.0):
    mat = bpy.data.materials.new(name)
    mat.use_nodes = True
    bsdf = mat.node_tree.nodes["Principled BSDF"]
    bsdf.inputs["Base Color"].default_value = color
    bsdf.inputs["Roughness"].default_value = roughness
    bsdf.inputs["Metallic"].default_value = metallic
    if emit is not None:
        bsdf.inputs["Emission Color"].default_value = emit
        bsdf.inputs["Emission Strength"].default_value = strength
    return mat


def ellipsoid(name, loc, scale, mat, rot=(0.0, 0.0, 0.0)):
    bpy.ops.mesh.primitive_uv_sphere_add(
        segments=96, ring_count=48, radius=1.0, location=loc, rotation=rot
    )
    obj = bpy.context.active_object
    obj.name = name
    obj.scale = scale
    obj.data.materials.append(mat)
    bpy.ops.object.shade_smooth()
    return obj


def arc(name, center, radius, start_deg, end_deg, mat, bevel=0.04, y_of=None):
    """Arco en el plano XZ (de cara al espectador) con grosor redondeado."""
    curve = bpy.data.curves.new(name, "CURVE")
    curve.dimensions = "3D"
    curve.bevel_depth = bevel
    curve.bevel_resolution = 8
    curve.resolution_u = 24
    cx, cy, cz = center
    steps = 24
    spline = curve.splines.new("POLY")
    spline.points.add(steps)
    for i in range(steps + 1):
        a = math.radians(start_deg + (end_deg - start_deg) * i / steps)
        px = cx + radius * math.cos(a)
        pz = cz + radius * math.sin(a)
        py = y_of(px, pz) if y_of else cy
        spline.points[i].co = (px, py, pz, 1.0)
    obj = bpy.data.objects.new(name, curve)
    bpy.context.collection.objects.link(obj)
    obj.data.materials.append(mat)
    return obj


def rounded_box(name, loc, size, mat, rot=(0.0, 0.0, 0.0), bevel=0.1):
    """Caja de esquinas redondeadas (bisel aplicado, sirve para exportar a GLB)."""
    bpy.ops.mesh.primitive_cube_add(size=1.0, location=loc, rotation=rot)
    obj = bpy.context.active_object
    obj.name = name
    obj.scale = size
    bpy.ops.object.transform_apply(location=False, rotation=False, scale=True)
    mod = obj.modifiers.new("Bisel", "BEVEL")
    mod.width = bevel
    mod.segments = 10
    mod.limit_method = "NONE"
    bpy.ops.object.modifier_apply(modifier="Bisel")
    obj.data.materials.append(mat)
    bpy.ops.object.shade_smooth()
    return obj


def blade(name, loc, size, mat, rot=(0.0, 0.0, 0.0)):
    """Panel turquesa en cuña: la punta se angosta y el borde frontal baja (picudo)."""
    bpy.ops.mesh.primitive_cube_add(size=1.0, location=loc, rotation=rot)
    obj = bpy.context.active_object
    obj.name = name
    obj.scale = size
    bpy.ops.object.transform_apply(location=False, rotation=False, scale=True)
    height = size[2]
    for v in obj.data.vertices:
        if v.co.z > 0:  # vértices de arriba: se juntan hacia una punta
            v.co.x *= 0.38
            v.co.y *= 0.45
            if v.co.y < 0:  # el frente baja: borde superior inclinado
                v.co.z -= 0.16 * height
    mod = obj.modifiers.new("Bisel", "BEVEL")
    mod.width = 0.03
    mod.segments = 4
    mod.limit_method = "NONE"
    bpy.ops.object.modifier_apply(modifier="Bisel")
    obj.data.materials.append(mat)
    bpy.ops.object.shade_smooth()
    return obj


def shield_outline():
    """Contorno de escudo (x, z): borde superior recto, esquinas redondeadas, fondo en U."""
    pts = []
    for i in range(13):  # borde superior, apenas arqueado
        x = -0.36 + 0.72 * i / 12
        pts.append((x, 0.34 + 0.015 * (1 - (x / 0.36) ** 2)))
    for i in range(1, 7):  # esquina superior derecha
        a = math.radians(90 - 90 * i / 6)
        pts.append((0.36 + 0.10 * math.cos(a), 0.24 + 0.10 * math.sin(a)))
    pts.append((0.46, 0.12))
    for i in range(1, 24):  # curva inferior, de derecha a izquierda
        ang = math.pi * i / 24
        pts.append(
            (
                0.46 * math.cos(ang) * (1 - 0.15 * math.sin(ang)),
                0.12 - 0.58 * math.sin(ang) ** 0.85,
            )
        )
    pts.append((-0.46, 0.12))
    for i in range(1, 6):  # esquina superior izquierda
        a = math.radians(180 - 90 * i / 6)
        pts.append((-0.36 + 0.10 * math.cos(a), 0.24 + 0.10 * math.sin(a)))
    return pts


def chest_plate(name, torso_center, torso_scale, plate_center_z, mat):
    """Placa turquesa en forma de escudo que copia la curvatura del torso."""
    cx, cy, cz = torso_center
    # Copia del torso un poco más grande: de ahí sale la superficie de la placa.
    shell = ellipsoid(
        name,
        torso_center,
        (torso_scale[0] + 0.03, torso_scale[1] + 0.03, torso_scale[2] + 0.03),
        mat,
    )

    # Prisma con el contorno de escudo, extruido hacia el frente (-Y).
    curve = bpy.data.curves.new(name + "Contorno", "CURVE")
    curve.dimensions = "2D"
    curve.fill_mode = "BOTH"
    curve.extrude = 0.5
    outline = shield_outline()
    spline = curve.splines.new("POLY")
    spline.points.add(len(outline) - 1)
    for i, (x, z) in enumerate(outline):
        spline.points[i].co = (x, z, 0.0, 1.0)
    spline.use_cyclic_u = True
    prism = bpy.data.objects.new(name + "Prisma", curve)
    bpy.context.collection.objects.link(prism)
    prism.location = (0, cy - 0.5, plate_center_z)
    prism.rotation_euler = (math.radians(90), 0, 0)
    bpy.context.view_layer.objects.active = prism
    prism.select_set(True)
    bpy.ops.object.convert(target="MESH")
    prism = bpy.context.active_object

    # Intersección: queda solo la parte del torso agrandado que cae dentro del escudo.
    bpy.context.view_layer.objects.active = shell
    boolean = shell.modifiers.new("Escudo", "BOOLEAN")
    boolean.operation = "INTERSECT"
    boolean.object = prism
    boolean.solver = "EXACT"
    bpy.ops.object.modifier_apply(modifier="Escudo")
    bpy.data.objects.remove(prism, do_unlink=True)

    bevel = shell.modifiers.new("Bisel", "BEVEL")
    bevel.width = 0.012
    bevel.segments = 3
    bevel.limit_method = "ANGLE"
    bpy.ops.object.modifier_apply(modifier="Bisel")
    return shell


def belt(name, z, rx, ry, mat, bevel=0.024):
    """Línea azul que rodea el torso por el frente, de un costado al otro."""
    curve = bpy.data.curves.new(name, "CURVE")
    curve.dimensions = "3D"
    curve.bevel_depth = bevel
    curve.bevel_resolution = 6
    spline = curve.splines.new("POLY")
    steps = 40
    spline.points.add(steps)
    for i in range(steps + 1):
        a = math.radians(185 + 170 * i / steps)  # arco frontal (y < 0)
        spline.points[i].co = (rx * math.cos(a), ry * math.sin(a), z, 1.0)
    obj = bpy.data.objects.new(name, curve)
    bpy.context.collection.objects.link(obj)
    obj.data.materials.append(mat)
    return obj


VISOR_CENTER = (0.0, -0.74, 0.08)
VISOR_SCALE = (0.8, 0.36, 0.5)


def visor_y(x, z, lift=0.014):
    """Coordenada Y de la superficie frontal del visor en (x, z), para pegar los ojos."""
    cx, cy, cz = VISOR_CENTER
    rx, ry, rz = VISOR_SCALE
    t = 1 - ((x - cx) / rx) ** 2 - ((z - cz) / rz) ** 2
    return cy - ry * math.sqrt(max(t, 0.0)) - lift


def dip_bottom(obj, amount=0.22, width=0.3):
    """Muesca suave en el borde inferior-centro: da la forma de frijol del visor."""
    for v in obj.data.vertices:
        if v.co.z < 0:
            v.co.z += amount * math.exp(-((v.co.x / width) ** 2)) * (-v.co.z)
    obj.data.update()


def visor_material():
    """Azul oscuro al centro, más claro hacia el borde y contorno cian brillante."""
    mat = bpy.data.materials.new("Visor")
    mat.use_nodes = True
    nt = mat.node_tree
    bsdf = nt.nodes["Principled BSDF"]
    bsdf.inputs["Roughness"].default_value = 0.06
    fresnel = nt.nodes.new("ShaderNodeFresnel")
    fresnel.inputs["IOR"].default_value = 1.7
    ramp = nt.nodes.new("ShaderNodeValToRGB")
    elements = ramp.color_ramp.elements
    elements[0].position = 0.0
    elements[0].color = (0.01, 0.05, 0.55, 1.0)
    elements[1].position = 1.0
    elements[1].color = (0.35, 0.95, 1.0, 1.0)
    mid = elements.new(0.6)
    mid.color = (0.06, 0.36, 1.0, 1.0)
    nt.links.new(fresnel.outputs["Fac"], ramp.inputs["Fac"])
    nt.links.new(ramp.outputs["Color"], bsdf.inputs["Base Color"])
    nt.links.new(ramp.outputs["Color"], bsdf.inputs["Emission Color"])
    bsdf.inputs["Emission Strength"].default_value = 1.1
    return mat


def surface_patch(name, center, scale, grow, patch_center, patch_radii, spin, mat):
    """Parche elíptico pegado a un elipsoide (copia agrandada recortada con un cilindro)."""
    shell = ellipsoid(
        name, center, tuple(c * (1 + grow) for c in scale), mat
    )
    bpy.ops.mesh.primitive_cylinder_add(
        vertices=64,
        radius=1.0,
        depth=2.0,
        location=(patch_center[0], center[1] - 0.28, patch_center[1]),
        rotation=(math.pi / 2, spin, 0),
    )
    cutter = bpy.context.active_object
    cutter.scale = (patch_radii[0], patch_radii[1], 0.45)
    bpy.context.view_layer.objects.active = shell
    boolean = shell.modifiers.new("Parche", "BOOLEAN")
    boolean.operation = "INTERSECT"
    boolean.object = cutter
    boolean.solver = "EXACT"
    bpy.ops.object.modifier_apply(modifier="Parche")
    bpy.data.objects.remove(cutter, do_unlink=True)
    return shell


def build_eyes(eye, halo, blink=False) -> None:
    """Ojos: arcos gruesos (∩) de puntas redondeadas con un halo azul detrás.
    Con blink=True los arcos se aplanan (ojos cerrados) para el parpadeo."""
    for name in ("HaloOjoIzq", "HaloOjoDer", "OjoIzq", "OjoDer"):
        old = bpy.data.objects.get(name)
        if old is not None:
            bpy.data.objects.remove(old, do_unlink=True)

    # Abiertos: arco corto y curvo. Cerrados: arco largo y casi recto, a la misma altura.
    radius, start, end, cz = (0.5, 72, 108, -0.42) if blink else (0.17, 35, 145, -0.05)
    for side, tag in ((-1, "Izq"), (1, "Der")):
        center = (side * 0.38, 0.0, cz)
        arc(f"HaloOjo{tag}", center, radius, start, end, halo, bevel=0.05 if blink else 0.085, y_of=lambda x, z: visor_y(x, z, 0.004))
        arc(f"Ojo{tag}", center, radius, start, end, eye, bevel=0.03 if blink else 0.05, y_of=lambda x, z: visor_y(x, z, 0.02))


def build_robot() -> None:
    white = material("Blanco", WHITE, roughness=0.32)
    frame = material("Marco", (0.74, 0.79, 0.84, 1.0), roughness=0.32)
    teal = material("Turquesa", TEAL, roughness=0.3)
    seam = material("Costura", (0.04, 0.16, 0.45, 1.0), roughness=0.4)
    dark = material("Oscuro", DARK, roughness=0.4)
    visor = visor_material()
    gloss = material("Reflejo", (1, 1, 1, 1), roughness=0.05)
    gloss.node_tree.nodes["Principled BSDF"].inputs["Alpha"].default_value = 0.26
    eye = material("Ojos", GLOW_EYE, roughness=0.2, emit=GLOW_EYE, strength=9.0)
    halo = material("HaloOjos", (0.1, 0.5, 1.0, 1), emit=(0.1, 0.55, 1.0, 1), strength=5.0)

    # Cabeza, tapa superior (bloque redondeado) y visor con marco (frente = -Y).
    ellipsoid("Cabeza", (0, 0, 0.0), (1.0, 0.9, 0.92), white)
    rounded_box("Tapa", (0, 0.08, 0.93), (0.62, 0.5, 0.34), white, bevel=0.12)
    dip_bottom(ellipsoid("MarcoVisor", (0, -0.7, 0.08), (0.87, 0.35, 0.58), frame))
    dip_bottom(ellipsoid("Visor", VISOR_CENTER, VISOR_SCALE, visor))

    # Reflejo translúcido arriba a la derecha, pegado a la curva del visor.
    surface_patch("ReflejoVisor", VISOR_CENTER, VISOR_SCALE, 0.012, (0.3, 0.3), (0.26, 0.11), -0.3, gloss)

    build_eyes(eye, halo)

    # Torso, placa de pecho turquesa y la costura azul que la bordea por abajo.
    ellipsoid("Torso", (0, 0, -1.3), (0.78, 0.66, 0.92), white)
    belt("LineaVientre", -1.06, 0.766, 0.647, seam)
    chest_plate("PlacaPecho", (0, 0, -1.3), (0.78, 0.66, 0.92), -1.12, teal)

    # Lados idénticos: oreja, bocina, panel turquesa y brazo colgando.
    for side, tag in ((-1, "Izq"), (1, "Der")):
        ellipsoid(f"Oreja{tag}", (side * 0.99, 0.06, -0.02), (0.2, 0.34, 0.34), white)
        ellipsoid(f"Bocina{tag}", (side * 1.165, 0.06, -0.02), (0.06, 0.14, 0.14), dark)
        blade(
            f"Panel{tag}",
            (side * 0.86, 0.1, 0.62),
            (0.24, 0.34, 0.85),
            teal,
            rot=(0, side * 0.12, 0),
        )
        # Brazo: pegado al costado del torso, con la mano hacia abajo.
        ellipsoid(
            f"Brazo{tag}",
            (side * 0.9, -0.08, -1.22),
            (0.27, 0.27, 0.52),
            white,
            rot=(0, -side * 0.3, 0),
        )


def add_lights() -> None:
    def area(name, loc, energy, color, size):
        data = bpy.data.lights.new(name, type="AREA")
        data.energy = energy
        data.color = color[:3]
        data.size = size
        obj = bpy.data.objects.new(name, data)
        obj.location = loc
        obj.visible_camera = False  # la luz ilumina pero no aparece en la imagen
        bpy.context.collection.objects.link(obj)

    area("Llave", (3.0, -4.5, 3.5), 260, (1, 1, 1, 1), 4.0)
    area("Relleno", (-4.0, -2.5, 1.0), 60, (0.8, 0.95, 1.0, 1), 5.0)
    area("Contraluz", (0.5, 4.0, 3.0), 180, (1, 1, 1, 1), 4.0)


def set_camera() -> None:
    target = bpy.data.objects.new("Objetivo", None)
    bpy.context.collection.objects.link(target)
    target.location = (0, 0, -0.5)

    camera_at = (0.0, -8.6, 0.35) if SPRITES else (2.4, -8.4, 0.6)
    bpy.ops.object.camera_add(location=camera_at)
    cam = bpy.context.active_object
    cam.data.lens = 85
    track = cam.constraints.new("TRACK_TO")
    track.target = target
    track.track_axis = "TRACK_NEGATIVE_Z"
    track.up_axis = "UP_Y"
    bpy.context.scene.camera = cam


def configure_world_and_render() -> None:
    scene = bpy.context.scene

    world = bpy.data.worlds.new("Fondo")
    world.use_nodes = True
    bg = world.node_tree.nodes["Background"]
    bg.inputs["Color"].default_value = WORLD_LIGHT
    bg.inputs["Strength"].default_value = 1.0
    scene.world = world

    # Fondo cian de marca: un plano emisivo detrás del robot, sin teñir la luz.
    # En modo sprites no se crea: el render sale con fondo transparente.
    if not SPRITES:
        make_backdrop()

    scene.render.engine = "CYCLES"
    scene.cycles.device = "CPU"
    scene.cycles.samples = SAMPLES
    scene.cycles.use_denoising = True
    scene.cycles.max_bounces = 6
    scene.view_settings.view_transform = "Standard"
    scene.render.resolution_x = SPRITE_SIZE if SPRITES else 1200
    scene.render.resolution_y = SPRITE_SIZE if SPRITES else 1200
    scene.render.film_transparent = SPRITES
    scene.render.image_settings.file_format = "PNG"
    scene.render.image_settings.color_mode = "RGBA" if SPRITES else "RGB"
    scene.render.filepath = PNG


def make_backdrop() -> None:
    backdrop_mat = bpy.data.materials.new("Fondo")
    backdrop_mat.use_nodes = True
    nodes = backdrop_mat.node_tree.nodes
    nodes.clear()
    emission = nodes.new("ShaderNodeEmission")
    emission.inputs["Color"].default_value = BACKDROP
    emission.inputs["Strength"].default_value = 1.0
    output = nodes.new("ShaderNodeOutputMaterial")
    backdrop_mat.node_tree.links.new(emission.outputs["Emission"], output.inputs["Surface"])
    bpy.ops.mesh.primitive_plane_add(size=40, location=(0, 6.0, 0), rotation=(math.radians(90), 0, 0))
    plane = bpy.context.active_object
    plane.name = "Fondo"
    plane.data.materials.append(backdrop_mat)


def clear_live() -> None:
    """Vacía la escena abierta sin tocar los add-ons (el servidor MCP sigue activo)."""
    for obj in list(bpy.data.objects):
        bpy.data.objects.remove(obj, do_unlink=True)
    for coll in (
        bpy.data.meshes,
        bpy.data.curves,
        bpy.data.materials,
        bpy.data.lights,
        bpy.data.cameras,
        bpy.data.worlds,
    ):
        for datablock in list(coll):
            coll.remove(datablock)


BODY_PARTS = ("Torso", "PlacaPecho", "LineaVientre", "BrazoIzq", "BrazoDer")


def remove_eyes() -> None:
    for name in ("HaloOjoIzq", "HaloOjoDer", "OjoIzq", "OjoDer"):
        old = bpy.data.objects.get(name)
        if old is not None:
            bpy.data.objects.remove(old, do_unlink=True)


def set_camera_visibility(names, visible) -> None:
    """Muestra u oculta piezas del render. Ocultar con hide_render (y no con visible_camera)
    evita que la zona de la otra capa salga como negro opaco; la sombra de contacto bajo la
    barbilla la dibuja la app en CSS."""
    for name in names:
        obj = bpy.data.objects.get(name)
        if obj is not None:
            obj.hide_render = not visible


def project(point):
    from bpy_extras.object_utils import world_to_camera_view
    from mathutils import Vector

    scene = bpy.context.scene
    co = world_to_camera_view(scene, scene.camera, Vector(point))
    return [co.x * SPRITE_SIZE, (1 - co.y) * SPRITE_SIZE]


def render_layer(scene, name) -> None:
    scene.render.filepath = os.path.join(SPRITE_DIR, name + ".png")
    bpy.ops.render.render(write_still=True)
    print(f"SPRITE_OK {scene.render.filepath}")


def render_sprites() -> None:
    import json

    os.makedirs(SPRITE_DIR, exist_ok=True)
    scene = bpy.context.scene
    bpy.context.view_layer.update()
    eye = bpy.data.materials["Ojos"]
    halo = bpy.data.materials["HaloOjos"]
    head_parts = [
        o.name
        for o in bpy.data.objects
        if o.type == "MESH" and o.name not in BODY_PARTS and o.name != "Fondo"
    ]

    # Posición de los ojos y del visor vista desde la cámara (para dibujarlos en SVG).
    eye_y = visor_y(0.38, 0.09)
    left = project((-0.38, eye_y, 0.09))
    right = project((0.38, eye_y, 0.09))
    unit = project((1.38, eye_y, 0.09))[0] - right[0]
    visor_front = visor_y(0.0, 0.08)
    layout = {
        "canvas": SPRITE_SIZE,
        "unit": unit,
        "eyeLeft": left,
        "eyeRight": right,
        "visor": project((0.0, visor_front, 0.08)),
        "visorRadius": [0.8 * unit, 0.5 * unit],
    }
    with open(os.path.join(SPRITE_DIR, "layout.json"), "w") as handle:
        json.dump(layout, handle, indent=2)

    # 1) Cabeza sin ojos (los ojos se dibujan en la app).
    remove_eyes()
    set_camera_visibility(BODY_PARTS, False)
    render_layer(scene, "head")

    # 2) Cuerpo.
    set_camera_visibility(BODY_PARTS, True)
    set_camera_visibility(head_parts, False)
    render_layer(scene, "body")

    # 3) Avatar estático: cabeza con ojos pintados (para listas de mensajes).
    set_camera_visibility(head_parts, True)
    set_camera_visibility(BODY_PARTS, False)
    build_eyes(eye, halo)
    render_layer(scene, "avatar")


def main() -> None:
    headless = bpy.app.background
    if headless:
        clear_scene()
    else:
        clear_live()

    build_robot()
    add_lights()
    set_camera()
    configure_world_and_render()

    if headless and SPRITES:
        render_sprites()
    elif headless:
        bpy.ops.wm.save_as_mainfile(filepath=BLEND)
        bpy.ops.export_scene.gltf(filepath=GLB, export_format="GLB")
        bpy.ops.render.render(write_still=True)
        print(f"RENDER_OK {PNG}")


main()
