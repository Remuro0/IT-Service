#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Генератор иконок пользователей для ИТ-Сервис
Создаёт аватарки с первой буквой имени
"""

from PIL import Image, ImageDraw, ImageFont
import os
import hashlib

class UserIconGenerator:
    """Генератор иконок пользователей"""
    
    def __init__(self, size=100):
        self.size = size
        self.colors = [
            '#6a0dad',  # Фиолетовый
            '#9b30ff',  # Светло-фиолетовый
            '#6ab7ff',  # Голубой
            '#00c853',  # Зелёный
            '#ff6b6b',  # Красный
            '#ff9800',  # Оранжевый
            '#e91e63',  # Розовый
            '#00bcd4',  # Бирюзовый
        ]
    
    def get_color_for_username(self, username):
        """Получить цвет на основе имени пользователя (стабильный)"""
        hash_value = int(hashlib.md5(username.encode()).hexdigest(), 16)
        return self.colors[hash_value % len(self.colors)]
    
    def create_icon(self, username, output_path=None):
        """
        Создать иконку пользователя
        
        Args:
            username: Имя пользователя
            output_path: Путь для сохранения (если None, вернуть Image объект)
        
        Returns:
            Image объект или None если сохранено в файл
        """
        # Создаём изображение
        img = Image.new('RGB', (self.size, self.size), color=self.get_color_for_username(username))
        draw = ImageDraw.Draw(img)
        
        # Получаем первую букву
        first_letter = username[0].upper() if username else '?'
        
        # Пытаемся загрузить шрифт
        try:
            # Для Windows
            if os.name == 'nt':
                font_path = 'C:/Windows/Fonts/arial.ttf'
            else:
                font_path = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
            
            font_size = int(self.size * 0.5)
            font = ImageFont.truetype(font_path, font_size)
        except:
            # Если шрифт не найден, используем стандартный
            font = ImageFont.load_default()
        
        # Вычисляем позицию текста для центрирования
        bbox = draw.textbbox((0, 0), first_letter, font=font)
        text_width = bbox[2] - bbox[0]
        text_height = bbox[3] - bbox[1]
        
        x = (self.size - text_width) // 2
        y = (self.size - text_height) // 2
        
        # Рисуем текст
        draw.text((x, y), first_letter, fill='white', font=font)
        
        # Сохраняем если указан путь
        if output_path:
            # Создаём директорию если нет
            os.makedirs(os.path.dirname(output_path), exist_ok=True)
            img.save(output_path, 'PNG')
            print(f"✅ Иконка сохранена: {output_path}")
            return None
        
        return img
    
    def create_default_icon(self, output_path='imang/default.png'):
        """Создать стандартную иконку по умолчанию"""
        img = Image.new('RGB', (self.size, self.size), color='#5a1a8f')
        draw = ImageDraw.Draw(img)
        
        # Рисуем силуэт пользователя
        center_x = self.size // 2
        center_y = self.size // 2
        
        # Голова (круг)
        head_radius = int(self.size * 0.15)
        head_y = int(self.size * 0.35)
        draw.ellipse(
            [center_x - head_radius, head_y - head_radius,
             center_x + head_radius, head_y + head_radius],
            fill='white'
        )
        
        # Тело (полукруг)
        body_radius = int(self.size * 0.25)
        body_y = int(self.size * 0.65)
        draw.ellipse(
            [center_x - body_radius, body_y - body_radius,
             center_x + body_radius, body_y + body_radius],
            fill='white'
        )
        
        # Сохраняем
        os.makedirs(os.path.dirname(output_path), exist_ok=True)
        img.save(output_path, 'PNG')
        print(f"✅ Иконка по умолчанию сохранена: {output_path}")
    
    def create_icons_for_users(self, users, output_dir='imang/users/'):
        """
        Создать иконки для списка пользователей
        
        Args:
            users: Список имён пользователей
            output_dir: Директория для сохранения
        """
        created = 0
        for username in users:
            output_path = f"{output_dir}{username}.png"
            if not os.path.exists(output_path):
                self.create_icon(username, output_path)
                created += 1
            else:
                print(f"⏭️ Пропущено (уже существует): {output_path}")
        
        print(f"\n✅ Создано {created} иконок")


# === Пример использования ===
if __name__ == '__main__':
    # Создаём генератор
    generator = UserIconGenerator(size=100)
    
    # Создаём иконку по умолчанию
    generator.create_default_icon()
    
    # Создаём иконки для тестовых пользователей
    test_users = ['admin', 'user', 'engineer', 'manager', 'guest']
    generator.create_icons_for_users(test_users)
    
    # Создаём одну иконку и показываем
    icon = generator.create_icon('Yegor')
    if icon:
        icon.show()  # Открыть в просмотрщике изображений